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

namespace OCA\UserBackendSqlRaw\Dbs;

use OCA\UserBackendSqlRaw\Db;
use \PDO;


class Mariadb extends Db {

	protected function createDbHandle() {
		return new PDO($this->assembleDsn(),
			$this->config->getDbUser(),
			$this->config->getDbPassword());
	}

	protected function assembleDsn() {
		if ($this->config->getDbSock()) {
			return 'mysql:unix_socket=' . $this->config->getDbSock()
			. ';dbname=' . $this->config->getDbName()
			. ';charset=' . $this->config->getMariadbCharset();
		} else {
			return 'mysql:host=' . $this->config->getDbHost()
			. ';port=' . $this->config->getDbPort()
			. ';dbname=' . $this->config->getDbName()
			. ';charset=' . $this->config->getMariadbCharset();
		}
	}
}
