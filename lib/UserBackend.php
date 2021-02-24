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
use OC\User\Backend;
use OCP\IUserManager;
use OCP\IGroupManager;

class UserBackend implements \OCP\IUserBackend, \OCP\UserInterface {

    /** @var LoggerInterface */
	private $logger;
	private $config;
	private $db;
	private $userManager;
	private $groupManager;

	public function __construct(LoggerInterface $logger, Config $config, Db $db, IUserManager $userManager, IGroupManager $groupManager) {
		$this->logger = $logger;
		$this->config = $config;
		// Don't get db handle (dbo object) here yet, so that it is only created
		// when db queries are actually run.
		$this->db = $db;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
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
		// prevent denial of service
		if (strlen($providedPassword) > Config::MAXIMUM_ALLOWED_PASSWORD_LENGTH) {
			return FALSE;
		}

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
		$wasUserDeleted = $this->executeOrCatchExceptionAndReturnFalse($statement, ['username' => $providedUsername]);
		return $wasUserDeleted;
	}

	public function getUsers($searchString = '', $limit = null, $offset = null) {
		// If the search string contains % or _ these would be interpreted as
		// wildcards in the LIKE expression. Therefore they will be escaped.
		$searchString = $this->escapePercentAndUnderscore($searchString);

		$queryFromConfig = $this->config->getQueryGetUsers();
		isset($limit) ? $limitSegment = ' LIMIT :limit' : $limitSegment = '';
		isset($offset) ? $offsetSegment = ' OFFSET :offset' : $offsetSegment = '';
		$finalQuery = $queryFromConfig . $limitSegment . $offsetSegment;

		$statement = $this->db->getDbHandle()->prepare($finalQuery);

		// Because MariaDB can not handle string parameters for LIMIT/OFFSET we have to bind the
		// values "manually" instead of passing an array to execute(). This is another instance of
		// MariaDB making the code "uglier".
		$statement->bindValue(':search', '%' . $searchString . '%', \PDO::PARAM_STR);
		if (isset($limit)) {
			$statement->bindValue(':limit', intval($limit), \PDO::PARAM_INT);
		}
		if (isset($offset))  {
			$statement->bindValue(':offset', intval($offset), \PDO::PARAM_INT);
		}
		$statement->execute();

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

		$this->updateAttributes($providedUsername);
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
		$parameterSubstitutions = [
			':username' => $username,
			':new_display_name' => $newDisplayName
		];

		$dbUpdateWasSuccessful =
			$this->executeOrCatchExceptionAndReturnFalse($statement, $parameterSubstitutions);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logger->error('Setting a new display name for username \''
				. $username . '\' failed, because the db update failed.');
			return FALSE;
		}
	}

	public function hasUserListings() {
		// There is no documentation or example code that actually uses this
		// method. It is assumed that listing is available if users can be
		// searched for without specifying any filters.
		return !empty($this->config->getQueryGetUsers());
	}

	public function setPassword($username, $newPassword) {
		// prevent denial of service
		if (strlen($newPassword) > Config::MAXIMUM_ALLOWED_PASSWORD_LENGTH) {
			$this->logPasswordLengthError($username);
			return FALSE;
		}

		if (!$this->userExists($username)) {
			return FALSE;
		}

		$newPasswordHash = $this->hashPassword($newPassword);
		if ($newPasswordHash === FALSE) {
			$this->logger->critical('Setting a new password failed,'
				. ' because the hashing function \''
				. $this->config->getHashAlgorithmForNewPasswords()
				. '\' failed.');
			return FALSE;
		}

		$dbHandle = $this->db->getDbHandle();
		// Don't throw exceptions on db errors because this could leak passwords
		// to logs.
		$dbHandle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
		$statement = $dbHandle->prepare($this->config->getQuerySetPasswordForUser());

		$parameterSubstitutions = [
			':username' => $username,
			':new_password_hash' => $this->hashPassword($newPassword)];

		$dbUpdateWasSuccessful =
			$this->executeOrCatchExceptionAndReturnFalse($statement, $parameterSubstitutions);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logger->error('Setting a new password for username \'' . $username
				. '\' failed, because the db update failed.');
			return FALSE;
		}
	}

	public function countUsers() {
		$statement = $this->db->getDbHandle()->query($this->config->getQueryCountUsers());
		$userCount = $statement->fetchColumn();
		return $userCount;
	}

	public function getHome($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryGetHome());
		$statement->execute(['username' => $providedUsername]);
		$retrievedHome = $statement->fetchColumn();
		return $retrievedHome;
	}


	public function createUser($providedUsername, $providedPassword) {
		// prevent denial of service
		if (strlen($providedPassword) > Config::MAXIMUM_ALLOWED_PASSWORD_LENGTH) {
			$this->logPasswordLengthError($providedUsername);
			return FALSE;
		}

		$dbHandle = $this->db->getDbHandle();

		$statement = $dbHandle->prepare($this->config->getQueryCreateUser());
		$dbUpdateWasSuccessful = $this->executeOrCatchExceptionAndReturnFalse($statement, [
			':username' => $providedUsername,
			':password_hash' => $this->hashPassword($providedPassword)]);

		if ($dbUpdateWasSuccessful) {
			return TRUE;
		} else {
			$this->logger->error('Creating the user with username \''
				. $providedUsername . '\' failed, because the db update failed.');
			return FALSE;

		}
	}

	public function updateAttributes($providedUsername) {
		$retrievedEmailAddress = null; 
		if(!empty($this->config->getQueryGetEmailAddress())) {
			$statement = $this->db->getDbHandle()->prepare($this->config->getQueryGetEmailAddress());
			$statement->execute(['username' => $providedUsername]);
			$newEmailAddress = $statement->fetchColumn();
		}
		$user = $this->userManager->get($providedUsername);

		$newGroups = null;
		if(!empty($this->config->getQueryGetGroups())) {
			$statement = $this->db->getDbHandle()->prepare($this->config->getQueryGetGroups());
			$statement->execute(['username' => $providedUsername]);
			$newGroups = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);

			// Make sure that the user is always in the "everyone" group
			if(!in_array('everyone', $newGroups)) {
				$newGroups[] = 'everyone';
			}
		}

		if ($user !== null) {
			$currentEmailAddress = (string)$user->getEMailAddress();
			if ($newEmailAddress !== null
				&& $currentEmailAddress !== $newEmailAddress) {
				$user->setEMailAddress($newEmailAddress);
			}

			if ($newGroups !== null) {
				$groupManager = $this->groupManager;
				$oldGroups = $groupManager->getUserGroupIds($user);

				$groupsToAdd = array_unique(array_diff($newGroups, $oldGroups));
				$groupsToRemove = array_diff($oldGroups, $newGroups);

				foreach ($groupsToAdd as $group) {
					if (!($groupManager->groupExists($group))) {
						$groupManager->createGroup($group);
					}
					$groupManager->get($group)->addUser($user);
				}

				foreach ($groupsToRemove as $group) {
					$groupManager->get($group)->removeUser($user);
				}
			}
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

	/**
	 * @param $password string the password to hash
	 * @return bool|string hashed password or FALSE on failure
	 */
	private function hashPassword($password) {
		$algorithmFromConfig = $this->config->getHashAlgorithmForNewPasswords();
		$hashedPassword = FALSE;

		// default algorithm is bcrypt
		if ($algorithmFromConfig === 'bcrypt' || empty($algorithmFromConfig)) {
			$hashedPassword = $this->hashWithModernMethod($password, PASSWORD_BCRYPT);
		} elseif ($algorithmFromConfig === 'argon2i') {
			$hashedPassword = $this->hashWithModernMethod($password, PASSWORD_ARGON2I);
		} elseif ($algorithmFromConfig === 'argon2id') {
			$hashedPassword = $this->hashWithModernMethod($password, PASSWORD_ARGON2ID);
		} elseif ($algorithmFromConfig === 'sha512'
			|| $algorithmFromConfig === 'sha256'
			|| $algorithmFromConfig === 'md5') {
			$hashedPassword = $this->hashWithOldMethod($password, $algorithmFromConfig);
		}
		return $hashedPassword;
	}

	/**
	 * Creates password with the modern password_hash() method. Supports Bcrypt, Argon2i
     * and Argon2id
	 * @param $password string the password to hash
	 * @param $algorithm int the algorithm to use for hashing the password
	 * @return bool|string the hashed password or FALSE on failure
	 */
	private function hashWithModernMethod($password, $algorithm) {
		$hashedPassword = password_hash($password, $algorithm);
		// Contrary to password_hash's documentation it also returns null if
		// an algorithm is not supported.
		if (is_null($hashedPassword)) {
			return FALSE;
		}
		return $hashedPassword;
	}

	/**
	 * Creates hashes using MD5-CRYPT, SHA-256-CRYPT or SHA-512-CRYPT using the
	 * the older method with "manual" creation of a salt.
	 * @param $password string the password to hash
	 * @param $algorithm string the algorithm to use for hashing the password
	 * @return bool|string the hashed password or FALSE on failure
	 */
	private function hashWithOldMethod($password, $algorithm) {
		$salt = base64_encode(random_bytes(8));
		$hashedPassword = FALSE;

		if ($algorithm === 'sha512') {
			$hashedPassword = crypt($password, '$6$' . $salt . '$');
		} elseif ($algorithm === 'sha256') {
			$hashedPassword = crypt($password, '$5$' . $salt . '$');
		} elseif ($algorithm === 'md5') {
			$hashedPassword = crypt($password, '$1$' . $salt . '$');
		}

		// If crypt() fails the returned string will be shorter than 13
		// characters, see http://php.net/manual/en/function.crypt.php.
		if (strlen($hashedPassword) < 13) {
			return FALSE;
		}
		return $hashedPassword;
	}

	/**
	 * Helper function that catches SQL exceptions for methods that should return FALSE on failure.
	 * If exception is not caught here, it bubbles up to Nextcloud's dispatcher and the user manager
	 * is not aware that a user backend method failed.
	 *
	 * @param \PDOStatement $pdoStatement the statement to execute
	 * @param array $parameterSubstitutions the substitution parameters
	 *
	 * @return bool|\PDOStatement
	 */
    private function executeOrCatchExceptionAndReturnFalse(\PDOStatement $pdoStatement, array $parameterSubstitutions)
    {
        try {
            $pdoStatement->execute($parameterSubstitutions);
        } catch (\PDOException $exception) {
            $this->logger->error('A SQL error occurred during a user_backend_sql_raw operation. '
                . 'See SQLSTATE exception above for details.'
                , ['exception' => $exception]);
            return FALSE;
        }
        return $pdoStatement;
    }

	/**
	 * Because this error is logged in two places, the lengthy error message is unified here.
	 * @param $username string username to mention in the error log
	 */
	private function logPasswordLengthError(string $username): void {
		$this->logger->error('Setting a new password for user \'' . $username
			. '\' was rejected because it is longer than ' . Config::MAXIMUM_ALLOWED_PASSWORD_LENGTH
			. ' characters. This is to prevent denial of service attacks against the server.');
	}
}
