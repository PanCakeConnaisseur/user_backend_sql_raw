<?php

namespace OCA\UserBackendSqlRaw;

use OC\User\Backend;
use OCP\IConfig;

class UserBackend implements \OCP\IUserBackend, \OCP\UserInterface {

	private $db;
	private $config;
	private $queryStrings;

	public function __construct(IConfig $config, Db $db) {
		$this->config = $config;
		// Don't get db handle (dbo object) here yet, so that it is only created
		// when db queries are actually run.
		$this->db = $db;

		$retrievedQueryStrings = $config->getSystemValue('user_backend_sql_raw')['queries'];

		$this->queryStrings = array(
			'getPasswordHashForUser' => $retrievedQueryStrings['getPasswordHashForUser'],
			'userExists' => $retrievedQueryStrings['userExists'],
		);
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
	 * @param $loginName
	 * @param $providedPassword
	 */
	public function checkPassword($providedUsername, $providedPassword) {
		$dbHandle = $this->db->getDbHandle();
		$statement = $dbHandle->prepare($this->queryStrings['getPasswordHashForUser']);
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
		$statement = $this->db->getDbHandle()->prepare($this->queryStrings['userExists']);
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