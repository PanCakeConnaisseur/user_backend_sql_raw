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

namespace OCA\UserBackendSqlRaw\Tests\Unit;

use OCA\UserBackendSqlRaw\Config;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ConfigTest extends TestCase
{
    private $logStub;
    private $nextcloudConfigStub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logStub = $this->createMock(LoggerInterface::class);
        $this->nextcloudConfigStub = $this->createMock(IConfig::class);
    }

    // Test that check whether exceptions are thrown if mandatory parameters are
    // missing

    public function testThrowsExceptionIfUserBackendSqlRawKeyIsNotSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array());

        $this->expectException(\UnexpectedValueException::class);
        new Config($this->logStub, $this->nextcloudConfigStub);
    }

    public function testThrowsExceptionIfMandatorySettingIsNotSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'the_configuration_is_not_empty' => 'but also contains no usable keys',
            ));

        $this->expectException(\UnexpectedValueException::class);
        $config = new Config($this->logStub, $this->nextcloudConfigStub);
        $config->getDsn();
    }

    public function testThrowsExceptionIfMandatorySettingIsEmpty()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'dsn' => '',
            ));

        $this->expectException(\UnexpectedValueException::class);
        $config = new Config($this->logStub, $this->nextcloudConfigStub);
        $config->getDsn();
    }

    public function testDefaultHashAlgorithmForNewPasswordsIsUsedWhenThisParameterIsNotSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'the_configuration_is_not_empty' => 'but also contains no usable keys',
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $expectedCharset = Config::DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS;
        $actualCharset = $config->getHashAlgorithmForNewPasswords();
        self::assertEquals($expectedCharset, $actualCharset);
    }

    public function testEmptyQueryParameterReturnsFalse()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'queries' => array(
                    'get_users' => '',
                ),
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $actualReturnValue = $config->getQueryGetUsers();
        self::assertSame(false, $actualReturnValue);
    }

    // Tests that check if actual (non-default) values are returned

    public function testDsnIsReturnedWhenThisParameterIsSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'dsn' => 'pgsql:host=/var/run/postgresql;dbname=theName_OfYourUserDb',
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $expectedDsn = 'pgsql:host=/var/run/postgresql;dbname=theName_OfYourUserDb';
        $actualDsn = $config->getDsn();
        self::assertEquals($expectedDsn, $actualDsn);
    }

    public function testPasswordIsReturnedWhenThisParameterIsSet()
    {
        $expectedPassword = '!I_am V-e rySecr3t9!&äßZ';
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'db_password' => $expectedPassword,
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $actualPassword = $config->getDbPassword();
        self::assertEquals($expectedPassword, $actualPassword);
    }

    public function testDBPasswordFromPasswordFileIsReturned()
    {

        $expectedPassword = 'v_erY-secr3ttt 909!&äßZ';

        $dbPasswordFile = tempnam("/tmp", "user_backend_sql_raw-db_password_file");
        if ($dbPasswordFile === false) {
            self::fail("Temporary db password file could not be created.");
        }

        $file = fopen($dbPasswordFile, "w");
        if ($file === false) {
            self::fail("Temporary db password file could not be opened for writing.");
        }

        fwrite($file, $expectedPassword);
        fclose($file);

        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'db_password_file' => $dbPasswordFile,
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $actualPassword = $config->getDbPassword();
        unlink($dbPasswordFile);
        self::assertEquals($expectedPassword, $actualPassword);
    }


    public function testDBPasswordFromPasswordFileIsTrimmed()
    {
        $expectedPassword = 'secret';

        $dbPasswordFile = tempnam("/tmp", "user_backend_sql_raw-db_password_file");
        if ($dbPasswordFile === false) {
            self::fail("Temporary db password file could not be created.");
        }

        $file = fopen($dbPasswordFile, "w");
        if ($file === false) {
            self::fail("Temporary db password file could not be opened for writing.");
        }

        // add tab in front and whitespace at the end which will be trimmed
        fwrite($file, "\t{$expectedPassword} ");
        fclose($file);

        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'db_password_file' => $dbPasswordFile,
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $actualPassword = $config->getDbPassword();
        unlink($dbPasswordFile);
        self::assertEquals($expectedPassword, $actualPassword);
    }

    public function testPasswordFromPasswordFileOverridesPasswordFromConfig()
    {
        $passwordFromFile = 'password_from_file';
        $passwordFromConfig = 'password_from_config';

        $dbPasswordFile = tempnam("/tmp", "user_backend_sql_raw-db_password_file");
        if ($dbPasswordFile === false) {
            self::fail("Temporary db password file could not be created.");
        }

        $file = fopen($dbPasswordFile, "w");
        if ($file === false) {
            self::fail("Temporary db password file could not be opened for writing.");
        }

        fwrite($file, "{$passwordFromFile}");
        fclose($file);

        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'db_password' => $passwordFromConfig,
                'db_password_file' => $dbPasswordFile,
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $actualPassword = $config->getDbPassword();
        unlink($dbPasswordFile);
        self::assertEquals($passwordFromFile, $actualPassword);
    }

    public function testHashAlgorithmForNewPasswordsIsReturnedWhenThisParameterIsSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'hash_algorithm_for_new_passwords' => 'SHA-512',
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $expectedHashAlgorithm = 'sha512';
        $actualHashAlgorithm = $config->getHashAlgorithmForNewPasswords();
        self::assertEquals($expectedHashAlgorithm, $actualHashAlgorithm);
    }

    public function testQueryIsReturnedWhenItIsSet()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'queries' => array(
                    'set_password_hash_for_user' => 'UPDATE virtual_users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
                ),
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        $expectedQuery = 'UPDATE virtual_users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)';
        $actualReturnValue = $config->getQuerySetPasswordForUser();
        self::assertEquals($expectedQuery, $actualReturnValue);
    }

    // Tests that check whether invalid values for countable types are
    // recognized

    public function testExceptionIsThrownForUnsupportedHashAlgorithmForNewPasswords()
    {
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'hash_algorithm_for_new_passwords' => 'des-3',
            ));

        $this->expectException(\UnexpectedValueException::class);
        $config = new Config($this->logStub, $this->nextcloudConfigStub);
        $config->getHashAlgorithmForNewPasswords();
    }

    // Test that checks if multiple parameters are recognized simultaneously.
    // Previous tests only tested single parameters.
    public function testMultipleParametersAreRecognizedSimultaneously()
    {
        $expectedDsn = 'pgsql:host=/var/run/postgresql;dbname=theName_OfYourUserDb';
        $expectedPassword = '!me SoSec35?äöß1';
        $this->nextcloudConfigStub->method('getSystemValue')
            ->willReturn(array(
                'dsn' => $expectedDsn,
                'db_password' => $expectedPassword,
                'queries' => array(
                    'user_exists' => 'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)',
                    'create_user' => 'INSERT INTO virtual_users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)',
                ),
                'hash_algorithm_for_new_passwords' => 'argon2i',
            ));

        $config = new Config($this->logStub, $this->nextcloudConfigStub);

        self::assertEquals($expectedDsn, $config->getDsn());
        self::assertEquals($expectedPassword, $config->getDbPassword());
        self::assertEquals(
            'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)'
            , $config->getQueryUserExists());
        self::assertEquals(
            'INSERT INTO virtual_users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)'
            , $config->getQueryCreateUser());
    }
}
