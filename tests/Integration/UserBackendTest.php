<?php
/**
 * @copyright Copyright (c) 2018 Alexey Abel <dev@abelonline.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserBackendSqlRaw\Tests\Integration;

use OCA\UserBackendSqlRaw\Config;
use OCA\UserBackendSqlRaw\Tests\Dbs\SqliteMemoryTestDb;
use OCA\UserBackendSqlRaw\UserBackend;
use OCP\AppFramework\App;
use OCP\App\IAppManager;
use OCP\IConfig;
use OC\User\User;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class UserBackendTest extends TestCase
{
    const APP_ID = 'user_backend_sql_raw';
    /** @var ContainerInterface */
    private $container;
    /** @var IAppManager */
    private $appManager;
    /** @var UserBackend */
    private $userBackend;
    /** @var \PDO */
    private $dbHandle;
    /** @var IConfig */
    private $nextcloudConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new \OCA\UserBackendSqlRaw\AppInfo\Application();
        $this->container = $app->getContainer();

        $this->nextcloudConfig = $this->container->get('OCP\IConfig');
        $this->nextcloudConfig->setSystemValue('instanceid', 'abcdefghijkl');

        $this->appManager = $this->container->get('OCP\App\IAppManager');

        $this->container->get('OCP\IUserManager')->clearBackends();
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig()
            , $this->getMockDb());
    }

    public function testAppCanBeEnabled()
    {
        if ($this->appManager->isInstalled(self::APP_ID)) {
            $this->appManager->disableApp(self::APP_ID);
        }
        $this->appManager->enableApp(self::APP_ID);
        $this->assertTrue($this->appManager->isInstalled(self::APP_ID));
    }

    public function testAppCanBeDisabled()
    {
        if (!$this->appManager->isInstalled(self::APP_ID)) {
            $this->appManager->enableApp(self::APP_ID);
        }
        $this->appManager->disableApp(self::APP_ID);
        $this->assertFalse($this->appManager->isInstalled(self::APP_ID));
    }

    public function testUserBackendCanBeRegistered()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);

        $registeredUserBackends = $this->container->get('OCP\IUserManager')->getBackends();
        $foundBackend = false;
        foreach ($registeredUserBackends as $userBackend) {
            if ($userBackend instanceof UserBackend) {
                $foundBackend = true;
                break;
            }
        }
        self::assertTrue($foundBackend);
    }

    public function testUserBackendNameIsCorrect()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);
        $registeredUserBackends = $this->container->get('OCP\IUserManager')->getBackends();

        foreach ($registeredUserBackends as $userBackend) {
            if ($userBackend instanceof UserBackend) {
                self::assertSame('SQL raw', $userBackend->getBackendName());
            }
        }

    }

    public function testExistingUserIsRecognizedByUsername()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);

        self::assertTrue($this->container->get('OCP\IUserManager')->userExists('alice'));
        self::assertTrue($this->container->get('OCP\IUserManager')->userExists('bob.robert@black123.com'));
        self::assertTrue($this->container->get('OCP\IUserManager')->userExists('chris.smith@black.org'));
    }

    public function testReturnedUserObjectsHaveCorrectUid()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $alice = $userManager->get('alice');
        self::assertSame('alice', $alice->getUID());

        $bob = $userManager->get('bob.robert@black123.com');
        self::assertSame('bob.robert@black123.com', $bob->getUID());

        $chris = $userManager->get('chris.smith@black.org');
        self::assertSame('chris.smith@black.org', $chris->getUID());

    }

    public function testExistingUserIsRecognizedByDisplayNameSubStrings()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('Alice');
        self::assertSame('alice', $searchResult[0]->getUID());

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('Alice Dorothea');
        self::assertSame('alice', $searchResult[0]->getUID());

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('Alice Dorothea Merkel');
        self::assertSame('alice', $searchResult[0]->getUID());

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('thea');
        self::assertSame('alice', $searchResult[0]->getUID());

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('Bobson-Jason');
        self::assertSame('bob.robert@black123.com', $searchResult[0]->getUID());
    }

    public function testUserSearchCanReturnMoreThanOneMatchedUser()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);

        $searchResult = $this->container->get('OCP\IUserManager')->search('black');
        self::assertArrayHasKey('bob.robert@black123.com', $searchResult);
        self::assertArrayHasKey('chris.smith@black.org', $searchResult);
        self::assertEquals(2, count($searchResult));
    }

    public function testDisplayNameSearchCanReturnMoreThanOneMatch()
    {
        $this->container->get('OCP\IUserManager')->registerBackend($this->userBackend);

        $searchResult = $this->container->get('OCP\IUserManager')->searchDisplayName('th');
        self::assertSame('Alice Dorothea Merkel', $searchResult[0]->getDisplayName());
        self::assertSame('Chris Smith', $searchResult[1]->getDisplayName());
        self::assertEquals(2, count($searchResult));
    }

    public function testWrongPasswordsAreRejected()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        // the correct password is "alice123"
        self::assertFalse($userManager->checkPassword('alice', 'alice'));
        self::assertFalse($userManager->checkPassword('alice', 'alice123 '));
        self::assertFalse($userManager->checkPassword('alice', ' alice123'));
        self::assertFalse($userManager->checkPassword('alice', 'alice123 more'));
        self::assertFalse($userManager->checkPassword('alice', ' "§$%&§'));
        self::assertFalse($userManager->checkPassword('alice', ''));
        self::assertFalse($userManager->checkPassword('alice', '1'));
        self::assertFalse($userManager->checkPassword('alice', 'true'));
        self::assertFalse($userManager->checkPassword('alice', 'TRUE'));
        self::assertFalse($userManager->checkPassword('alice', '\''));
        self::assertFalse($userManager->checkPassword('alice', '\'\''));
        self::assertFalse($userManager->checkPassword('alice', '\\'));
        self::assertFalse($userManager->checkPassword('alice', "\0"));

        self::assertFalse($userManager->checkPassword('bob.robert@black123.com', 'alice123'));
        self::assertFalse($userManager->checkPassword('non-existing-user', 'alice123'));
    }

    public function testPasswordInMd5FormatCanBeSetAndChecked()
    {
        // change user backend to use md5
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('md5')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $1$ and therefore
        // is a MD5-CRYPT hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$1$', $retrievedPasswordHash, 'Saved password hash is not in MD5-CRYPT format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using MD5-CRYPT failed.');
    }

    public function testPasswordInSha256FormatCanBeSetAndChecked()
    {
        // change user backend to use sha256
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('sha256')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $5$ and therefore
        // is a SHA256-CRYPT hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$5$', $retrievedPasswordHash, 'Saved password hash is not in SHA256-CRYPT format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using SHA256-CRYPT failed.');
    }

    public function testPasswordInSha512FormatCanBeSetAndChecked()
    {
        // change user backend to use sha512
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('sha512')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $6$ and therefore
        // is a SHA512-CRYPT hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$6$', $retrievedPasswordHash, 'Saved password hash is not in SHA512-CRYPT format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using SHA512-CRYPT failed.');
    }

    public function testPasswordInBcryptFormatCanBeSetAndChecked()
    {
        // change user backend to use bcrypt
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('bcrypt')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $2y$ and therefore
        // is a bcrypt hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$2y$', $retrievedPasswordHash, 'Saved password hash is not in Bcrypt format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using Bcrypt failed.');
    }

    /**
     * @requires PHP 7.2
     */
    public function testPasswordInArgon2iFormatCanBeSetAndChecked()
    {
        // change user backend to use argon2i
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('argon2i')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $argon2i$ and therefore
        // is a argon2i hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$argon2i$', $retrievedPasswordHash, 'Saved password hash is not in argon2i format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using Argon2i failed.');
    }

    /**
     * @requires PHP 7.3
     */
    public function testPasswordInArgon2idFormatCanBeSetAndChecked()
    {
        // change user backend to use argon2id
        $this->userBackend = new UserBackend($this->getLogStub()
            , $this->getMockAppConfig('argon2id')
            , $this->getMockDb());

        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = '%ran!34;;;!783-_';

        $userObject = $userManager->get($usernameForTest);
        self::assertTrue($userObject->setPassword($passwordForTest));

        // check that password hash in db actually starts with $argon2id$ and therefore
        // is a argon2id hash
        $statement = $this->dbHandle->prepare('SELECT password_hash FROM users WHERE username = :username');
        $statement->execute(['username' => $usernameForTest]);
        $retrievedPasswordHash = $statement->fetchColumn();
        self::assertStringStartsWith('$argon2id$', $retrievedPasswordHash, 'Saved password hash is not in argon2id format');

        // check that Nextcloud's password checking algorithm. Nextcloud returns
        // the user object if password check succeeded.
        $actualUser = $userManager->checkPassword('alice', $passwordForTest);
        self::assertSame($userObject, $actualUser, 'Password check using Argon2id failed.');
    }

    public function testPasswordsThatAreLongerThan100CharactersAreRejectedWithFalse()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'alice';
        $passwordForTest = str_repeat('0123456789', 11);

        $userObject = $userManager->get($usernameForTest);
        $setPasswordResult = $userObject->setPassword($passwordForTest);
        self::assertFalse($setPasswordResult);

        $checkPasswordResult = $userManager->checkPassword('alice', $passwordForTest);
        self::assertFalse($checkPasswordResult);
    }

    public function testUserCanBeCreatedAndCanLogin()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $usernameForTest = 'newuser@example.com';
        $passwordForTest = '%ran!34;;;!783-_';

        self::assertInstanceOf(User::class
            , $userManager->createUser($usernameForTest, $passwordForTest));
        self::assertTrue($userManager->userExists('newuser@example.com'));

        $expectedUserObject = $userManager->get('newuser@example.com');
        $retrievedUserObject = $userManager->checkPassword($usernameForTest, $passwordForTest);
        self::assertSame($expectedUserObject, $retrievedUserObject);
    }

    public function testUserCanBeDeletedAndCanNotLoginAfterwards()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        // can login before deletion (alice already exists in mock database)
        $expectedUserObject = $userManager->get('alice');
        $retrievedUserObject = $userManager->checkPassword('alice', 'alice123');
        self::assertSame($expectedUserObject, $retrievedUserObject);

        // delete alice
        $expectedUserObject->delete();
        self::assertFalse($userManager->userExists('alice'));

        // check that alice can not login anymore
        $retrievedUserObject = $userManager->checkPassword('alice', 'alice123');
        self::assertSame(false, $retrievedUserObject);
    }

    public function testDisplayNameOfUserCanBeChanged()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $aliceUserObject = $userManager->get('alice');
        self::assertTrue($aliceUserObject->setDisplayName('Alice Alisson-Balisson'));
        self::assertSame('Alice Alisson-Balisson', $aliceUserObject->getDisplayName());
    }

    public function testDisplayNameCanContainNonAsciiCharacters()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $aliceUserObject = $userManager->get('alice');
        self::assertTrue($aliceUserObject
                ->setDisplayName('Aliçé Alissôn-Balìsßon the 3rd van Høuten фжщй'));
        self::assertSame('Aliçé Alissôn-Balìsßon the 3rd van Høuten фжщй'
            , $aliceUserObject->getDisplayName());
    }

    public function testUsersCanBeCounted()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        $countResult = $userManager->countUsers();
        self::assertArrayHasKey('SQL raw', $countResult);
        self::assertEquals(3, $countResult['SQL raw']);
    }

    public function testUserBackendSaysThatItHasUserListings()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        self::assertTrue($this->userBackend->hasUserListings());
    }

    public function testHomeFolderForUserIsReturned()
    {
        $userManager = $this->container->get('OCP\IUserManager');
        $userManager->registerBackend($this->userBackend);

        self::assertSame('alice'
            , $userManager->get('alice')->getHome());
        self::assertSame('/home/bob'
            , $userManager->get('bob.robert@black123.com')->getHome());
        self::assertSame('home/chris/Nextcloud'
            , $userManager->get('chris.smith@black.org')->getHome());
    }

    //TODO: Test implementsActions()

    private function getLogStub()
    {
        return $this->getMockBuilder(LoggerInterface::class)->getMock();
    }

    private function getMockAppConfig($passwordHashForNewPasswords = 'bcrypt'): Config
    {
        $nextcloudConfigStub = $this
            ->getMockBuilder(\OCP\IConfig::class)->getMock();

        $nextcloudConfigStub->method('getSystemValue')
            ->willReturn($this->getMockAppConfigurationArray($passwordHashForNewPasswords));
        return new Config($this->getLogStub(), $nextcloudConfigStub);
    }

    /**
     * Returns data that is usually found in config.php. The password for new
     * password can be overridden to test multiple hash algorithms.
     *
     * @param string $passwordHashForNewPasswords the password algorithm to use
     * for new passwords
     *
     * @return array the mock configuration
     */
    private function getMockAppConfigurationArray($passwordHashForNewPasswords = 'bcrypt')
    {
        return array(
            'db_user' => 'will not be used but prevents exception',
            'db_password' => 'will not be used but prevents exception',
            'db_host' => 'will not be used but prevents exception',
            'queries' => array(
                'get_password_hash_for_user' => 'SELECT password_hash FROM users WHERE username = :username',
                'user_exists' => 'SELECT EXISTS(SELECT 1 FROM users WHERE username = :username)',
                'get_users' => 'SELECT username FROM users WHERE (username LIKE :search COLLATE NOCASE) OR (display_name LIKE :search COLLATE NOCASE)',
                'set_password_hash_for_user' => 'UPDATE users SET password_hash = :new_password_hash WHERE username = :username',
                'delete_user' => 'DELETE FROM users WHERE username = :username',
                'get_display_name' => 'SELECT display_name FROM users WHERE username = :username',
                'set_display_name' => 'UPDATE users SET display_name  = :new_display_name WHERE username = :username',
                'count_users' => 'SELECT COUNT (*) FROM users',
                'get_home' => 'SELECT home FROM users WHERE username = :username',
                'create_user' => 'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)',
            ),
            'hash_algorithm_for_new_passwords' => $passwordHashForNewPasswords,
        );
    }

    /**
     * Returns a in-memory sqlite db that is prefilled with three users
     * @return SqliteMemoryTestDb a in-memory sqlite db with three users
     */
    private function getMockDb(): SqliteMemoryTestDb
    {
        try {
            $mockDb = new SqliteMemoryTestDb($this->getMockAppConfig());
            $this->dbHandle = $mockDb->getDbHandle();
        } catch (\PDOException $e) {
            throw new \PDOException('Could not create sqlite db. You '
                . 'probably need to install the sqlite driver for php (package '
                . 'php7.0-sqlite3 on Debian based systems).');
        }
        // COLLATE nocase because sqlite does not have ILIKE
        $this->dbHandle->exec('CREATE TABLE users (
							username TEXT COLLATE nocase,
							password_hash TEXT,
							display_name TEXT COLLATE nocase,
							home TEXT,
							PRIMARY KEY(username)
						);
						');

        // the passwords are: alice123, bob123 and chris123 respectively
        $this->dbHandle->exec('INSERT INTO users (username, password_hash, display_name, home) VALUES
    (\'alice\',\'$6$thisIsASalt$8LDk8b12ZDHHkT8MQ6tVWSCGRPYXfiByg/7oMgqyS9hYs1SCo8rGVUygyy3no856vFOkrfYjzX5tI2/EF0vNG.\',\'Alice Dorothea Merkel\',\'alice\'),
    (\'bob.robert@black123.com\',\'$6$thisIsASalt$dYNRPo7BIIKEuiOgx8yk82yWVatsyqHvG9deGf0GRfxlVOobAicPisy8deiprrjgGOWxZjeJnqLsykDlX6lBp1\',\'Bob Bobson-Jason\',\'/home/bob\'),
    (\'chris.smith@black.org\',\'$6$thisIsASalt$8AiFnSCWRO5YzAMCkEBO/L25dNzb70HfNFxfc8gbGiuF.oD5p7pirt0fez5elFbKPsu10lx2F9ToNVyXuHv25.\',\'Chris Smith\',\'home/chris/Nextcloud\');
');
        return $mockDb;
    }
}
