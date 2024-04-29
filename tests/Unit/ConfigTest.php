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


final class ConfigTest extends TestCase {

	private $logStub;
	private $nextcloudConfigStub;

	protected function setUp(): void {
		parent::setUp();
		$this->logStub = $this->createMock(LoggerInterface::class);
		$this->nextcloudConfigStub = $this->createMock(IConfig::class);
	}


	// Test that check whether exceptions are thrown if mandatory parameters are
	// missing

	public function testThrowsExceptionIfUserBackendSqlRawKeyIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array());

		$this->expectException(\UnexpectedValueException::class);
		new Config($this->logStub, $this->nextcloudConfigStub);
	}

	public function testThrowsExceptionIfMandatorySettingIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$this->expectException(\UnexpectedValueException::class);
		$config = new Config($this->logStub, $this->nextcloudConfigStub);
		$config->getDbName();
	}

	public function testThrowsExceptionIfMandatorySettingIsEmpty() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_name' => ''
			));

		$this->expectException(\UnexpectedValueException::class);
		$config = new Config($this->logStub, $this->nextcloudConfigStub);
		$config->getDbName();
	}

	public function testThrowsExceptionIfDbPasswordAndDbPasswordFileAreBothSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_password' => 'such_secret',
				// Specify a file that is always there, so that test does not fail due to missing db password file.
				'db_password_file' => '/dev/zero'
			));

		$this->expectException(\UnexpectedValueException::class);
		$config = new Config($this->logStub, $this->nextcloudConfigStub);
		$config->getDbPassword();
	}



	// Tests that check if default values are uses correctly

	public function testDefaultDbTypeIsUsedWhenThatParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedDbType = Config::DEFAULT_DB_TYPE;
		$actualDbType = $config->getDbType();
		self::assertEquals($expectedDbType, $actualDbType);
	}

	public function testDefaultHostIsUsedWhenThisParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedHost = Config::DEFAULT_DB_HOST;
		$actualHost = $config->getDbHost();
		self::assertEquals($expectedHost, $actualHost);
	}

	public function testDefaultPostgresqlPortIsUsedWhenThisParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedPort = Config::DEFAULT_POSTGRESQL_PORT;
		$actualPort = $config->getDbPort();
		self::assertEquals($expectedPort, $actualPort);
	}

	public function testDefaultMariaPortIsUsedWhenThisParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_type' => 'mariadb'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedPort = Config::DEFAULT_MARIADB_PORT;
		$actualPort = $config->getDbPort();
		self::assertEquals($expectedPort, $actualPort);
	}

	public function testDefaultMariadbCharsetIsUsedWhenThisParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedCharset = Config::DEFAULT_MARIADB_CHARSET;
		$actualCharset = $config->getMariadbCharset();
		self::assertEquals($expectedCharset, $actualCharset);
	}

	public function testDefaultHashAlgorithmForNewPasswordsIsUsedWhenThisParameterIsNotSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'the_configuration_is_not_empty' => 'but also contains no usable keys'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedCharset = Config::DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS;
		$actualCharset = $config->getHashAlgorithmForNewPasswords();
		self::assertEquals($expectedCharset, $actualCharset);
	}

	public function testEmptyQueryParameterReturnsFalse() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'queries' => array(
					'get_users' => ''
				)
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$actualReturnValue = $config->getQueryGetUsers();
		self::assertSame(FALSE, $actualReturnValue);
	}

	// Tests that check if actual (non-default) values are returned

	public function testDbTypeIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_type' => 'mariadb'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedDbType = 'mariadb';
		$actualDbType = $config->getDbType();
		self::assertEquals($expectedDbType, $actualDbType);
	}

	public function testDbHostDomainNameIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_host' => 'nextcloud.mycompany.com'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedHost = 'nextcloud.mycompany.com';
		$actualHost = $config->getDbHost();
		self::assertEquals($expectedHost, $actualHost);
	}

	public function testDbHostIpIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_host' => '43.100.4.0'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedHost = '43.100.4.0';
		$actualHost = $config->getDbHost();
		self::assertEquals($expectedHost, $actualHost);
	}

	public function testDbPortIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_port' => '54321'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedPort = '54321';
		$actualPort = $config->getDbPort();
		self::assertEquals($expectedPort, $actualPort);
	}

	public function testDBPasswordFromPasswordFileIsTrimmedAndReturned() {

		$expectedPassword = 'very-secret 909!&äßZ';

		$db_password_file = tempnam("/tmp", "user_backend_sql_raw-db_password_file");
		if($db_password_file === FALSE) {
			self::fail("Temporary db password file could not be created.");
		}

		$file = fopen($db_password_file, "w");
		if($file === FALSE) {
		self::fail("Temporary db password file could not be opened for writing.");
		}

		// add whitespace at the end which will be trimmed
		fwrite($file, "{$expectedPassword} ");
		fclose($file);

		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_password_file' => $db_password_file
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$actualPassword = $config->getDbPassword();
		unlink($db_password_file);
		self::assertEquals($expectedPassword, $actualPassword);
	}

	public function testMariaDbCharsetIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'mariadb_charset' => 'latin2_czech_cs'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedCharset = 'latin2_czech_cs';
		$actualCharset = $config->getMariadbCharset();
		self::assertEquals($expectedCharset, $actualCharset);
	}

	public function testHashAlgorithmForNewPasswordsIsReturnedWhenThisParameterIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'hash_algorithm_for_new_passwords' => 'SHA-512'
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedHashAlgorithm = 'sha512';
		$actualHashAlgorithm = $config->getHashAlgorithmForNewPasswords();
		self::assertEquals($expectedHashAlgorithm, $actualHashAlgorithm);
	}

	public function testQueryIsReturnedWhenItIsSet() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'queries' => array(
					'set_password_hash_for_user' => 'UPDATE virtual_users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)'
				)
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		$expectedQuery = 'UPDATE virtual_users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)';
		$actualReturnValue = $config->getQuerySetPasswordForUser();
		self::assertEquals($expectedQuery, $actualReturnValue);
	}


	// Test that check whether invalid values four countable types are
	// recognized

	public function testExceptionIsThrownForUnsupportedHashAlgorithmForNewPasswords() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'hash_algorithm_for_new_passwords' => 'des-3'
			));

		$this->expectException(\UnexpectedValueException::class);
		$config = new Config($this->logStub, $this->nextcloudConfigStub);
		$config->getDbPassword();
	}

	public function testExceptionIsThrownForUnsupportedDbType() {
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_type' => 'oracle'
			));

		$this->expectException(\UnexpectedValueException::class);
		$config = new Config($this->logStub, $this->nextcloudConfigStub);
		$config->getDbType();
	}

	// Test that checks if multiple parameters are recognized simultaneously.
	// Previous tests only tested single parameters.
	public function testMultipleParametersAreRecognizedSimultaneously() {
		// db_type will be left empty to test default value
		$this->nextcloudConfigStub->method('getSystemValue')
			->willReturn(array(
				'db_type' => '',
				'db_port' => '4567',
				'db_user' => 'JohnDoe',
				'db_password' => 'bpd_N(z6%aT&$<Km',
				'db_host' => 'db.cluster.de',
				'queries' =>
					array(
						'user_exists' => 'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)',
						'create_user' => 'INSERT INTO virtual_users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)',
					),
				'hash_algorithm_for_new_passwords' => 'argon2i',
			));

		$config = new Config($this->logStub, $this->nextcloudConfigStub);

		self::assertEquals('postgresql', $config->getDbType());
		self::assertEquals('4567', $config->getDbPort());
		self::assertEquals('JohnDoe', $config->getDbUser());
		self::assertEquals('bpd_N(z6%aT&$<Km', $config->getDbPassword());
		self::assertEquals('db.cluster.de', $config->getDbHost());
		self::assertEquals(
			'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)'
			, $config->getQueryUserExists());
		self::assertEquals(
			'INSERT INTO virtual_users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)'
			, $config->getQueryCreateUser());
	}
}
