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

use \PDO;

class Db {

	/** @var Config  */
	private $config;

	/** @var PDO */
	private $dbHandle;

	public function __construct(Config $config) {
		$this->config = $config;
	}

	/**
	 * Returns a db handle (PDO object).
	 * @return PDO a PDO object that is used for database access
	 */
	public function getDbHandle() {
		if (is_null($this->dbHandle)) {
			$dsn = $this->assembleDsnForPostgresql();
			$dbHandle = new PDO($dsn);
			$dbHandle->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
			$dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// Some methods of the backend are called by Nextcloud in a way that
			// suppresses exceptions, probably to avoid leaking passwords to log
			// files. Therefore it is not necessary to change PDO::ATTR_ERRMODE for
			// these manually. These methods are (as of Nextcloud 13.0.1):
			// createUser(). But not setPassword(). Because checkPassword() only
			// retrieves the hash it does not suffer from this problem at all.

			// only assign when setup was successful
			$this->dbHandle = $dbHandle;
		}
		// Some methods change the error mode, therefore it needs to be reset to
		// the default value.
		$this->dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $this->dbHandle;
	}


	/**
	 * Returns a Data Source Name (DSN) that is used by a PDO object for
	 * creating the connection to the database.
	 * @return string the DSN string
	 */
	private function assembleDsnForPostgresql() {
		return 'pgsql:host=' . ($this->config->getDbHost() ?? Config::DEFAULT_DB_HOST)
			. ';port=' . ($this->config->getDbPort() ?? Config::DEFAULT_DB_PORT)
			. ';dbname=' . $this->config->getDbName()
			. ';user=' . $this->config->getDbUser()
			. ';password=' . $this->config->getDbPassword();
	}
}
