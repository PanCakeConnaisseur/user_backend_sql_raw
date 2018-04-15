<?php

namespace OCA\UserBackendSqlRaw;

use \OCP\IConfig;
use OCP\ILogger;
use \PDO;


class Db {
	const DEFAULT_DB_HOST = 'localhost';
	const DEFAULT_DB_PORT = '5432';

	const CONFIG_KEY = 'user_backend_sql_raw';
	const CONFIG_KEY_DB_HOST = 'dbHost';
	const CONFIG_KEY_DB_PORT = 'dbPort';
	const CONFIG_KEY_DB_NAME = 'dbName';
	const CONFIG_KEY_DB_USER = 'dbUser';
	const CONFIG_KEY_DB_PASSWORD = 'dbPassword';

	private $logger;
	private $nextCloudConfiguration;
	private $appConfiguration;

	public function __construct(ILogger $logger, IConfig $config) {
		$this->logger = $logger;
		$this->nextCloudConfiguration = $config;
	}

	/**
	 * Returns a db handle (PDO object). The configuration is read and validated
	 * only here so that this does not happend every time the DB object is
	 * created without actually doing DB queries.
	 * @return PDO a PDO object that is used for database access
	 */
	public function getDbHandle() {
		$this->readAppConfiguration();
		$this->checkAppConfigurationAndLogErrors();

		$dsn = $this->assembleDsnForPostgresql();
		$dbHandle = new PDO($dsn);
		return $dbHandle;
	}

	/**
	 * Reads the app's configuration from the nextcloud configuration
	 * (config/config.php).
	 */
	private function readAppConfiguration() {
		$this->appConfiguration = $this->nextCloudConfiguration->getSystemValue(self::CONFIG_KEY);
	}

	/**
	 * Checks the configuration that was read from config.php and logs errors if
	 * configuration keys are missing. Because port and host have default values
	 * their absence will be only logged with info severity.
	 */
	private function checkAppConfigurationAndLogErrors() {
		// mandatory keys
		if (empty($this->appConfiguration)) {
			$this->logger->critical('The Nextcloud configuration (config/config.php) does not contain the key '
				. self::CONFIG_KEY . ' which is should contain this apps configuration.');
		} else {
			if (empty($this->appConfiguration[self::CONFIG_KEY_DB_NAME])) {
				$this->logger->critical($this->errorMessageForMandatorySubkey(self::CONFIG_KEY_DB_NAME));
			}
			if (empty($this->appConfiguration[self::CONFIG_KEY_DB_USER])) {
				$this->logger->critical($this->errorMessageForMandatorySubkey(self::CONFIG_KEY_DB_USER));
			}
			if (empty($this->appConfiguration[self::CONFIG_KEY_DB_PASSWORD])) {
				$this->logger->critical($this->errorMessageForMandatorySubkey(self::CONFIG_KEY_DB_PASSWORD));
			}
			// optional keys
			if (empty($this->appConfiguration[self::CONFIG_KEY_DB_HOST])) {
				$this->logger->info('The config key ' . self::CONFIG_KEY_DB_HOST
					. ' is not set, defaulting to host ' . self::DEFAULT_DB_HOST);
			}
			if (empty($this->appConfiguration[self::CONFIG_KEY_DB_PORT])) {
				$this->logger->info('The config key ' . self::CONFIG_KEY_DB_PORT
					. ' is not set, defaulting to port ' . self::DEFAULT_DB_PORT);
			}
		}
	}

	/**
	 * Returns a full error message and hint for mandatory subkeys.
	 * @param $subkeyName string the name of the subkey
	 * @return string the full error message and hint
	 */
	private function errorMessageForMandatorySubkey($subkeyName) {
		return 'The config key ' . $subkeyName . ' is not set. Add it to config/config.php as a subkey of '
			. self::CONFIG_KEY . '.';
	}

	/**
	 * Returns a Data Source Name (DSN) that is used by a PDO object for
	 * creating the connection to the database.
	 * @return string the DSN string
	 */
	private function assembleDsnForPostgresql() {
		return 'pgsql:host=' . ($this->appConfiguration[self::CONFIG_KEY_DB_HOST] ?? self::DEFAULT_DB_HOST)
			. ';port=' . ($this->appConfiguration[self::CONFIG_KEY_DB_PORT] ?? self::DEFAULT_DB_PORT)
			. ';dbname=' . $this->appConfiguration[self::CONFIG_KEY_DB_NAME]
			. ';user=' . $this->appConfiguration[self::CONFIG_KEY_DB_USER]
			. ';password=' . $this->appConfiguration[self::CONFIG_KEY_DB_PASSWORD];
	}
}
