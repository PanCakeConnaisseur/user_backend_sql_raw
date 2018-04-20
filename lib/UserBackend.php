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

use OCP\ILogger;
use OC\User\Backend;

class UserBackend implements \OCP\IUserBackend, \OCP\UserInterface {

	private $logger;
	private $logContext = ['app' => 'user_backend_sql_raw'];
	private $config;
	private $db;

	public function __construct(ILogger $logger, Config $config, Db $db) {
		$this->logger = $logger;
		$this->config = $config;
		// Don't get db handle (dbo object) here yet, so that it is only created
		// when db queries are actually run.
		$this->db = $db;
	}

	public function getBackendName() {
		return 'SQL raw';
	}

	public function implementsActions($actions) {

		return (bool)((
				(!empty($this->config->getQueryCreateUser()) ? Backend::CREATE_USER : 0)
				| (!empty($this->config->getQuerySetPasswordForUser()) ? Backend::SET_PASSWORD : 0)
				| ($this->queriesForUserLoginAreSet() ? Backend::CHECK_PASSWORD : 0)
				| (!empty($this->config->getQueryGetHome()) ? Backend::GET_HOME : 0)
				| (!empty($this->config->getQueryGetDisplayName()) ? Backend::GET_DISPLAYNAME : 0)
				| (!empty($this->config->getQuerySetDisplayName()) ? Backend::SET_DISPLAYNAME : 0)
				| (!empty($this->config->getQueryCountUsers()) ? Backend::COUNT_USERS : 0)
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

	public function deleteUser($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryDeleteUser());
		$wasUserDeleted = $statement->execute(['username' => $providedUsername]);
		return $wasUserDeleted;
	}

	public function getUsers($searchString = '', $limit = null, $offset = null) {
		// If the search string contains % or _ these would be interpreted as
		// wildcards in the LIKE expression. Therefore they will be escaped.
		$searchString = $this->escapePercentAndUnderscore($searchString);

		$parameterSubstitution['username'] = '%' . $searchString . '%';

		if (is_null($limit)) {
			$limitSegment = '';
		} else {
			$limitSegment = ' LIMIT :limit';
			$parameterSubstitution['limit'] = $limit;
		}

		if (is_null($offset)) {
			$offsetSegment = '';
		} else {
			$offsetSegment = ' OFFSET :offset';
			$parameterSubstitution['offset'] = $offset;
		}

		$queryFromConfig = $this->config->getQueryGetUsers();

		$finalQuery = '(' . $queryFromConfig . ')' . $limitSegment . $offsetSegment;

		$statement = $this->db->getDbHandle()->prepare($finalQuery);
		$statement->execute($parameterSubstitution);
		// Setting the second parameter to 0 will ensure, that only the first
		// column is returned.
		$matchedUsers = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
		return $matchedUsers;

	}

	public function userExists($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryUserExists());
		$statement->execute(['username' => $providedUsername]);
		$doesUserExist = $statement->fetchColumn();
		return $doesUserExist;
	}

	public function getDisplayName($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryGetDisplayName());
		$statement->execute(['username' => $providedUsername]);
		$retrievedDisplayName = $statement->fetchColumn();
		return $retrievedDisplayName;
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$matchedUsers = $this->getUsers($search, $limit, $offset);
		$displayNames = array();
		foreach ($matchedUsers as $matchedUser) {
			$displayNames[$matchedUser] = $this->getDisplayName($matchedUser);
		}
		return $displayNames;
	}

	public function setDisplayName($username, $newDisplayName) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQuerySetDisplayName());
		$dbUpdateWasSuccessful = $statement->execute([
			':username' => $username,
			':new_display_name' => $newDisplayName]);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logContext[] = $statement->errorInfo();
			$this->logger->error('Setting a new display name for username \'' . $username . '\' failed, because the db update failed.' . print_r($statement->errorInfo()),
				$this->logContext);
			return FALSE;
		}
	}

	public function hasUserListings() {
		// There is no documentation or example code that actually uses this
		// method. It is assumed that listing is available if users can be
		// searched for without specifying any filters.
		return !empty($this->config->getQueryGetUsers());
	}

	public function setPassword($username, $password) {
		if (!$this->userExists($username)) {
			return FALSE;
		}

		$dbHandle = $this->db->getDbHandle();
		// Don't throw exceptions on db errors because this could leak passwords
		// to logs.
		$dbHandle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
		$statement = $dbHandle->prepare($this->config->getQuerySetPasswordForUser());

		$dbUpdateWasSuccessful = $statement->execute([
			':username' => $username,
			':new_password_hash' => $this->hashPassword($password)]);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logger->error('Setting a new password for username \'' . $username . '\' failed, because the db update failed.',
				$this->logContext);
			return FALSE;
		}
	}

	public function countUsers() {
		$statement = $this->db->getDbHandle()->query($this->config->getQueryCountUsers());
		$userCount =  $statement->fetchColumn();
		return $userCount;
	}

	public function getHome($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryGetHome());
		$statement->execute(['username' => $providedUsername]);
		$retrievedHome = $statement->fetchColumn();
		return $retrievedHome;
	}


	public function createUser($providedUsername, $providedPassword) {
		$dbHandle = $this->db->getDbHandle();

		$statement = $dbHandle->prepare($this->config->getQueryCreateUser());
		$dbUpdateWasSuccessful = $statement->execute([
			':username' => $providedUsername,
			':password_hash' => $this->hashPassword($providedPassword)]);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logger->error('Creating the user with username \'' . $providedUsername . '\' failed, because the db update failed.',
				$this->logContext);
			return FALSE;

		}
	}

	/**
	 * Escape % and _ with \.
	 *
	 * @param $input string the input that will be escaped
	 * @return string input string with % and _ escaped
	 */
	private function escapePercentAndUnderscore($input) {
		return str_replace('%', '\\%', str_replace('_', '\\_', $input));
	}

	/**
	 * @return bool whether configuration contains a query for getting a
	 * password hash and a query to check if a user exists
	 */
	private function queriesForUserLoginAreSet() {
		return (!empty($this->config->getQueryGetPasswordHashForUser())
			&& !empty($this->config->getQueryUserExists()));
	}

	private function hashPassword($password) {
		// By default strong bcrypt hashing will be used but if the user
		// specified Config::CONFIG_KEY_HASH_ALGORITHM then this will be used
		// instead. This enables support for older software that does not
		// understand bcrypt.
		if (empty($this->config->getHashAlgorithmForNewPasswords())) {
			return $this->hashWithBcrypt($password);
		} else {
			return $this->hashWithOther($password);
		}
	}



	/**
	 * @param $password string the password to hash
	 * @return bool|string the hashed password or FALSE on failure
	 */
	private function hashWithBcrypt($password) {
		// Set the password hash type (PASSWORD_BCRYPT) explicitly so that
		// it does not change in future PHP versions. Other software using the
		// user db might not be able to read a newer hash string.
		$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
		if ($hashedPassword === FALSE) {
			$this->logger->error('Setting a new password failed, because the hashing function failed.',
				$this->logContext);
			return FALSE;
		}

		return $hashedPassword;
	}

	/**
	 * Creates hashes using MD5-CRYPT, SHA-256-CRYPT or SHA-512-CRYPT
	 * @param $password string the password to hash
	 * @return bool|string the hashed password or FALSE on failure
	 */
	private function hashWithOther($password) {
		$salt = base64_encode(random_bytes(8));
		$hashedPassword = FALSE;

		$hashFunctionFromConfig = $this->config->getHashAlgorithmForNewPasswords();

		if ($hashFunctionFromConfig === 'sha512') {
			$hashedPassword = crypt($password, '$6$' . $salt . '$');
		} elseif ($hashFunctionFromConfig === 'sha256') {
			$hashedPassword = crypt($password, '$5$' . $salt . '$');
		} elseif ($hashFunctionFromConfig === 'md5') {
			$hashedPassword = crypt($password, '$1$' . $salt . '$');
		}

		// If crypt() fails the returned string will be FALSE or shorter than 13
		// characters, see http://php.net/manual/en/function.crypt.php.
		if ($hashedPassword === FALSE || strlen($hashedPassword) < 13) {
			$this->logger->error('Setting a new password failed,'
				. ' because the hashing function ' . $hashFunctionFromConfig
				. ' failed.', $this->logContext);
			return FALSE;
		}
		return $hashedPassword;
	}
}