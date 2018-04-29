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

namespace OCA\UserBackendSqlRaw\AppInfo;

use \OCP\AppFramework\App;
use \OCA\UserBackendSqlRaw\Dbs\Mariadb;
use \OCA\UserBackendSqlRaw\Dbs\Postgresql;

class Application extends App {

	public function __construct(array $urlParams = array()) {
		parent::__construct('user_backend_sql_raw', $urlParams);

		$container = $this->getContainer();

		$container->registerService('OCA\UserBackendSqlRaw\Db', function ($c) {
			/** @var \OCA\UserBackendSqlRaw\Config $config */
			$config = $c->query('OCA\UserBackendSqlRaw\Config');

			if ($config->getDbType() === 'mariadb') {
				return new Mariadb($config);
			} else {
				// PostgreSQL is default
				return new Postgresql($config);
			}
		});

		/** Nextcloud's dependency injection framework will take care of the
		 * instantiation of all the arguments for the UserBackend class and
		 * their dependencies. The only thing that can not be automatically
		 * instantiated is the Db class because it is abstract. This is what the
		 * above registerService('OCA\UserBackendSqlRaw\Db...) is defined for.
		 * It tells the DI framework what to do if Db needs to instantiated for
		 * an argument.
		 */
		$userBackendSqlRaw = \OC::$server
			->query(\OCA\UserBackendSqlRaw\UserBackend::class);

		\OC::$server->getUserManager()->registerBackend($userBackendSqlRaw);
	}
}