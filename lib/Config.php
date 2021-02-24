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

namespace OCA\UserBackendSqlRaw;

use Psr\Log\LoggerInterface;
use \OCP\IConfig;

class Config {

	const DEFAULT_DB_TYPE = 'postgresql';
	const DEFAULT_DB_HOST = 'localhost';
	const DEFAULT_POSTGRESQL_PORT = '5432';
	const DEFAULT_MARIADB_PORT = '3306';
	const DEFAULT_MARIADB_CHARSET ='utf8mb4';
	const DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS = 'bcrypt';

	const MAXIMUM_ALLOWED_PASSWORD_LENGTH = 100;

	const CONFIG_KEY = 'user_backend_sql_raw';
	const CONFIG_KEY_DB_TYPE = 'db_type';
	const CONFIG_KEY_DB_HOST = 'db_host';
	const CONFIG_KEY_DB_PORT = 'db_port';
	const CONFIG_KEY_DB_NAME = 'db_name';
	const CONFIG_KEY_DB_USER = 'db_user';
	const CONFIG_KEY_DB_PASSWORD = 'db_password';
	const CONFIG_KEY_MARIADB_CHARSET = 'mariadb_charset';
	const CONFIG_KEY_HASH_ALGORITHM_FOR_NEW_PASSWORDS = 'hash_algorithm_for_new_passwords';

	const CONFIG_KEY_QUERIES = 'queries';
	const CONFIG_KEY_GET_PASSWORD_HASH_FOR_USER = 'get_password_hash_for_user';
	const CONFIG_KEY_USER_EXISTS = 'user_exists';
	const CONFIG_KEY_GET_USERS = 'get_users';
	const CONFIG_KEY_SET_PASSWORD_HASH_FOR_USER = 'set_password_hash_for_user';
	const CONFIG_KEY_DELETE_USER = 'delete_user';
	const CONFIG_KEY_GET_DISPLAY_NAME = 'get_display_name';
	const CONFIG_KEY_SET_DISPLAY_NAME = 'set_display_name';
	const CONFIG_KEY_COUNT_USERS = 'count_users';
	const CONFIG_KEY_GET_HOME = 'get_home';
	const CONFIG_KEY_CREATE_USER = 'create_user';
	const CONFIG_KEY_GET_EMAIL_ADDRESS = 'get_email_address';
	const CONFIG_KEY_GET_GROUPS = 'get_groups';

	/* @var LoggerInterface */
	private $logger;
	private $appConfiguration;

	public function __construct(LoggerInterface $logger, IConfig $nextCloudConfiguration) {
		$this->logger = $logger;

		$this->appConfiguration = $nextCloudConfiguration->getSystemValue(self::CONFIG_KEY);
		if (empty($this->appConfiguration)) {
			throw new \UnexpectedValueException('The Nextcloud '
				.'configuration (config/config.php) does not contain the key '
				. self::CONFIG_KEY . ' which should contain the configuration '
				.'for the app user_backend_sql_raw.');
		}
	}

	/**
	 * @return string db type to connect to
	 */
	public function getDbType() {
		$dbTypeFromConfig = $this->getConfigValueOrDefaultValue(self::CONFIG_KEY_DB_TYPE
			,self::DEFAULT_DB_TYPE);

		$normalizedDbType = $this->normalize($dbTypeFromConfig);

		if (!$this->dbTypeIsSupported($normalizedDbType)) {
			throw new \UnexpectedValueException('The config key '
				. self::CONFIG_KEY_DB_TYPE . ' is set to '.$dbTypeFromConfig.'. This '
				.'value is invalid. Only postgresql and mariadb are supported.');
		}

		return $normalizedDbType;
	}

	/**
	 * @return string db host to connect to
	 */
	public function getDbHost() {
		return $this->getConfigValueOrDefaultValue(self::CONFIG_KEY_DB_HOST
			,self::DEFAULT_DB_HOST);
	}

	/**
	 * @return int db port to connect to
	 */
	public function getDbPort() {

		$defaultPortForCurrentDb = ($this->getDbType() === 'mariadb')
			? self::DEFAULT_MARIADB_PORT
			: self::DEFAULT_POSTGRESQL_PORT;

		return $this->getConfigValueOrDefaultValue(self::CONFIG_KEY_DB_PORT
			, $defaultPortForCurrentDb);
	}

	/**
	 * @return string db name to connect to
	 */
	public function getDbName() {
		return $this->getConfigValueOrThrowException(self::CONFIG_KEY_DB_NAME);
	}

	/**
	 * @return string db user to connect as
	 */
	public function getDbUser() {
		return $this->getConfigValueOrThrowException(self::CONFIG_KEY_DB_USER);
	}

	/**
	 * @return string password of db user
	 */
	public function getDbPassword() {
		return $this->getConfigValueOrThrowException(self::CONFIG_KEY_DB_PASSWORD);
	}

	/**
	 * @return string charset for mariadb connection
	 */
	public function getMariadbCharset() {
		return $this->getConfigValueOrDefaultValue(self::CONFIG_KEY_MARIADB_CHARSET
			, self::DEFAULT_MARIADB_CHARSET);
	}

	/**
	 * @return string hash algorithm to be used for password generation
	 */
	public function getHashAlgorithmForNewPasswords() {
		$hashAlgorithmFromConfig = $this->getConfigValueOrDefaultValue
		(self::CONFIG_KEY_HASH_ALGORITHM_FOR_NEW_PASSWORDS
			, self::DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS);

		$normalizedHashAlgorithm = $this->normalize($hashAlgorithmFromConfig);

		if (!$this->hashAlgorithmIsSupported($normalizedHashAlgorithm)) {
			throw new \UnexpectedValueException('The config key '
				. self::CONFIG_KEY_HASH_ALGORITHM_FOR_NEW_PASSWORDS. ' is set '
				.'to '.$hashAlgorithmFromConfig.'. This value is invalid. Only '
				.'md5, sha256, sha512, bcrypt, argon2i and argon2id are supported.');
		}

		if ($normalizedHashAlgorithm === 'argon2i'
			&& version_compare(PHP_VERSION, '7.2.0', '<')) {
			throw new \UnexpectedValueException(
				'You specified Argon2i as the hash algorithm for new '
				.'passwords. Argon2i is only available in PHP version 7.2.0 and'
				.' higher, but your PHP version is '.PHP_VERSION.'.');
		}

        if ($normalizedHashAlgorithm === 'argon2id'
            && version_compare(PHP_VERSION, '7.3.0', '<')) {
            throw new \UnexpectedValueException(
                'You specified Argon2id as the hash algorithm for new '
                .'passwords. Argon2id is only available in PHP version 7.3.0 and'
                .' higher, but your PHP version is '.PHP_VERSION.'.');
        }

		return $normalizedHashAlgorithm;
	}


	public function getQueryGetPasswordHashForUser() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_PASSWORD_HASH_FOR_USER);
	}

	public function getQueryUserExists() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_USER_EXISTS);
	}

	public function getQueryGetUsers() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_USERS);
	}

	public function getQuerySetPasswordForUser() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_SET_PASSWORD_HASH_FOR_USER);
	}

	public function getQueryDeleteUser() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_DELETE_USER);
	}

	public function getQueryGetDisplayName() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_DISPLAY_NAME);
	}

	public function getQuerySetDisplayName() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_SET_DISPLAY_NAME);
	}

	public function getQueryCountUsers() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_COUNT_USERS);
	}

	public function getQueryGetHome() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_HOME);
	}

	public function getQueryCreateUser() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_CREATE_USER);
	}

	public function getQueryGetEmailAddress() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_EMAIL_ADDRESS);
	}

	public function getQueryGetGroups() {
		return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_GROUPS);
	}

	/**
	 * Tries to read a config value and throws an exception if it is not set.
	 * This is used for config keys that are mandatory.
	 * @param $configKey string key name of configuration parameter
	 * @return string|array the value of the configuration parameter, which also
	 * can be an array (for queries).
	 * @throws \UnexpectedValueException
	 */
	private function getConfigValueOrThrowException($configKey) {
		if (empty($this->appConfiguration[$configKey])) {
			$errorMessage = 'The config key ' . $configKey . ' is not set. Add it'
				. ' to config/config.php as a subkey of ' . self::CONFIG_KEY . '.';
			throw new \UnexpectedValueException($errorMessage);
		} else {
			return $this->appConfiguration[$configKey];
		}
	}

	/**
	 * Tries to read a config value and if it is set returns its value,
	 * otherwise returns provided value. Also logs a debug message that default
	 * value was used. This is used for config keys that are optional and where
	 * sensible default values are known.
	 * @param $configKey string key name of configuration parameter
	 * @param $defaultValue string default parameter that will be returned if
	 * config key is not set
	 * @return string value of config key or provided default value
	 */
	private function getConfigValueOrDefaultValue($configKey, $defaultValue) {
		if (empty($this->appConfiguration[$configKey])) {
			$this->logger->debug('The config key ' . $configKey
				. ' is not set, defaulting to ' . $defaultValue . '.');
			return $defaultValue;
		} else {
			return $this->appConfiguration[$configKey];
		}
	}

	/**
	 * Tries to read a config value (query) and if it is set returns its value,
	 * otherwise returns FALSE. This is used for optional configuration keys
	 * where default values are not known, i.e. SQL queries.
	 * @param $configKey string key name of configuration parameter
	 * @return string|bool value of configuration parameter or false if it is
	 * not set
	 */
	private function getQueryStringOrFalse ($configKey) {
		$queryArray = $this->getConfigValueOrThrowException(self::CONFIG_KEY_QUERIES);

		if (empty($queryArray[$configKey])) {
			return FALSE;
		}
		else {
			return $queryArray[$configKey];
		}
	}

	/**
	 * @param $dbType string db descriptor to check
	 * @return bool whether the db is supported
	 */
	private function dbTypeIsSupported($dbType) {
		return $dbType === 'postgresql'
			|| $dbType === 'mariadb';
	}

	/**
	 * Checks whether hash algorithm is supported for writing.
	 * @param $hashAlgorithm string hash algorithm descriptor to check
	 * @return bool whether hash algorithm is supported
	 */
	private function hashAlgorithmIsSupported($hashAlgorithm) {
		return $hashAlgorithm === 'md5'
			|| $hashAlgorithm === 'sha256'
			|| $hashAlgorithm === 'sha512'
			|| $hashAlgorithm === 'bcrypt'
			|| $hashAlgorithm === 'argon2i'
			|| $hashAlgorithm === 'argon2id';
	}

	/**
	 * Removes hyphens and underscores from input and makes it lowercase.
	 * Used for hash algorithms, in case a user enters 'sha-512' or
	 * 'PostgreSQL'.
	 * @param $string string string to normalize
	 * @return string lowercase input with hyphens and underscores removed
	 */
	private function normalize($string) {
		return strtolower(preg_replace("/[-_]/", "", $string));
	}
}