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

use OC\User\Backend;

class UserBackend implements \OCP\IUserBackend, \OCP\UserInterface {

	private $db;
	private $config;

	public function __construct(Config $config, Db $db) {
		$this->config = $config;
		// Don't get db handle (dbo object) here yet, so that it is only created
		// when db queries are actually run.
		$this->db = $db;
	}

	public function getBackendName() {
		return 'SQL raw';
	}

	public function implementsActions($actions) {
		return (bool)((Backend::CHECK_PASSWORD
			) & $actions);
	}

	/**
	 * Checks provided login name and password against the database. This method
	 * is not part of \OCP\UserInterface but is called by Manager.php of
	 * Nextcloud if Backend::CHECK_PASSWORD is set.
	 * @param $providedUsername
	 * @param $providedPassword
	 * @return bool whether the provided password was correct for provided user
	 */
	public function checkPassword($providedUsername, $providedPassword) {
		$dbHandle = $this->db->getDbHandle();
		$statement = $dbHandle->prepare($this->config->getQueryGetPasswordHashForUser());
		$statement->execute(['username' => $providedUsername]);
		$retrievedPasswordHash = $statement->fetchColumn();

		if ($retrievedPasswordHash === FALSE) {
			return FALSE;
		}

		if (password_verify($providedPassword, $retrievedPasswordHash)) {
			return $providedUsername;
		} else {
			return FALSE;
		}
	}

	public function deleteUser($uid) {
		// TODO: Implement deleteUser() method.
	}

	public function getUsers($search = '', $limit = null, $offset = null) {
		// TODO: Implement getUsers() method.
	}

	public function userExists($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryUserExists());
		$statement->execute(['username' => $providedUsername]);
		$doesUserExist = $statement->fetchColumn();
		return $doesUserExist;
	}

	public function getDisplayName($uid) {
		// TODO: Implement getDisplayName() method.
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		// TODO: Implement getDisplayNames() method.
	}

	public function hasUserListings() {
		// TODO: Implement hasUserListings() method.
	}
}