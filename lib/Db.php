<?php

namespace OCA\UserBackendSqlRaw;

use \OCP\IConfig;
use \PDO;


class Db {

	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @return PDO a PDO object that is used for database access
	 */
	public function getDbHandle() {
		$dsn = $this->assembleDsnForPostgresql();
		$dbHandle = new PDO($dsn);
		return $dbHandle;
	}

	/**
	 * Returns a Data Source Name (DSN) that is used by PDO for creating the
	 * connection to the database.
	 * @return string the DSN string
	 */
	private function assembleDsnForPostgresql() {

		$retrievedConfiguration = $this->config->getSystemValue('user_backend_sql_raw');
		//TODO: handle non-existent or incomplete configuration

		return 'pgsql:host=' . ($retrievedConfiguration['dbHost'] ?? 'localhost')
			. ';port=' . ($retrievedConfiguration['dbPort'] ?? '5432')
			. ';dbname=' . $retrievedConfiguration['dbName']
			. ';user=' . $retrievedConfiguration['dbUser']
			. ';password=' . $retrievedConfiguration['dbPassword'];
	}
}
