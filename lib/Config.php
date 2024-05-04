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

class Config
{
    const DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS = 'bcrypt';
    const MAXIMUM_ALLOWED_PASSWORD_LENGTH = 100;

    const CONFIG_KEY = 'user_backend_sql_raw';
    const CONFIG_KEY_DSN = 'dsn';
    const CONFIG_KEY_DB_USER = 'db_user';
    const CONFIG_KEY_DB_PASSWORD = 'db_password';
    const CONFIG_KEY_DB_PASSWORD_FILE = 'db_password_file';
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

    /* @var LoggerInterface */
    private $logger;
    private $appConfiguration;

    /*
     * Design decision: Judging from the Nextcloud debug logs the Config class is
     * constructed at least as often as queries to the DB are made. Therefore,
     * reading all config options once and then returning them from the runtime
     * object would not yield any performance advantage. On the contrary, most
     * options would be read but never used. So, lazy loading all options seems
     * to be the better way.
     */
    public function __construct(LoggerInterface $logger, IConfig $nextCloudConfiguration)
    {
        $this->logger = $logger;
        $this->appConfiguration = $nextCloudConfiguration->getSystemValue(self::CONFIG_KEY);
        if (empty($this->appConfiguration)) {
            throw new \UnexpectedValueException('The Nextcloud '
                . 'configuration (config/config.php) does not contain the key '
                . self::CONFIG_KEY . ' which should contain the configuration '
                . 'for the app user_backend_sql_raw.');
        }

        $this->warnAboutObsoleteConfigKeys();
    }

    public function warnAboutObsoleteConfigKeys()
    {
        $obsolete_keys = array("db_type", "db_host", "db_port", "db_name", "mariadb_charset");
        foreach ($obsolete_keys as $key) {
            // not using getConfigValueOrFalse() here, because we want to also catch empty strings
            if (array_key_exists(key: $key, array:$this->appConfiguration)) {
                $this->logger->warning("The configuration key '{$key}' is obsolete since "
                    . "version 2.0.0. It has no effect and can be removed.");
            }
        }
    }

    /**
     * @return string dsn to use for db connection
     * @throws \UnexpectedValueException
     */
    public function getDsn()
    {
        return $this->getConfigValueOrThrowException(self::CONFIG_KEY_DSN);
    }

    /**
     * @return string db user to connect as
     */
    public function getDbUser()
    {
        return $this->getConfigValueOrFalse(self::CONFIG_KEY_DB_USER);
    }

    // Used instead of getDbPassword() when only needs to check if `db_password`
    // and not `db_password_file` or password in DSN is set.
    public function dbPasswordInConfigIsSet() : bool {
        return $this->getConfigValueOrFalse(self::CONFIG_KEY_DB_PASSWORD) !== false;
    }

    /**
     * @return string password of db user
     * @throws \UnexpectedValueException
     */
    public function getDbPassword()
    {
        $password = $this->getConfigValueOrFalse(self::CONFIG_KEY_DB_PASSWORD);
        $passwordFilePath = $this->getConfigValueOrFalse(self::CONFIG_KEY_DB_PASSWORD_FILE);

        $passwordIsSet = $password !== false;
        $passwordFileIsSet = $passwordFilePath !== false;

        // Password from file (db_password_file) has higher priority than password from config (db_password).
        if ($passwordFileIsSet) {
            $this->logger->debug("Will read db password stored in file " . $passwordFilePath)
            . ". Password from config file will not be considered. Password from DSN still has "
            ."priority.";
            $error_message_prefix = "Specified db password file with path {$passwordFilePath}";

            if (!file_exists($passwordFilePath)) {
                throw new \UnexpectedValueException("{$error_message_prefix} does not exist or is not accessible.");
            }
            if (is_link($passwordFilePath)) {
                throw new \UnexpectedValueException("{$error_message_prefix} is a symbolic link, which might be a security problem and is therefore not allowed.");
            }
            if (is_dir($passwordFilePath)) {
                throw new \UnexpectedValueException("{$error_message_prefix} is a directory but I need a file to read the password.");
            }
            $file = fopen($passwordFilePath, "r");
            if ($file === false) {
                throw new \UnexpectedValueException("{$error_message_prefix} can not be opened. Maybe insufficient permissions?");
            }
            // + 1 because fgets() reads one less byte than specified and we want to keep the promise of reading 100 bytes
            $first_line = fgets($file, self::MAXIMUM_ALLOWED_PASSWORD_LENGTH + 1);
            if ($first_line === false) {
                fclose($file);
                throw new \UnexpectedValueException("{$error_message_prefix} was opened but the first line could not be read.");
            }
            fclose($file);
            $this->logger->debug("Successfully read db password from file " . $passwordFilePath) . ".";
            return trim($first_line);
        } elseif ($passwordIsSet) {
            $this->logger->debug("Will read db password specified in config.php. Password from file"
            ." was not specified. Password from DSN still has priority.");
            return $password;
        } else {
            return false;
        }

        // Priority of password in the DSN over both passwords read here is
        // implemented in the PDO implementation of PHP. It will simply ignore
        // the password given as a parameter during PDO object creation and use
        // the one from the DSN, if the DSN contains it.

    }

    /**
     * @return string hash algorithm to be used for password generation
     */
    public function getHashAlgorithmForNewPasswords()
    {
        $hashAlgorithmFromConfig = $this->getConfigValueOrDefaultValue
            (self::CONFIG_KEY_HASH_ALGORITHM_FOR_NEW_PASSWORDS
            , self::DEFAULT_HASH_ALGORITHM_FOR_NEW_PASSWORDS);

        $normalizedHashAlgorithm = $this->normalize($hashAlgorithmFromConfig);

        if (!$this->hashAlgorithmIsSupported($normalizedHashAlgorithm)) {
            throw new \UnexpectedValueException('The config key '
                . self::CONFIG_KEY_HASH_ALGORITHM_FOR_NEW_PASSWORDS . ' is set '
                . 'to ' . $hashAlgorithmFromConfig . '. This value is invalid. Only '
                . 'md5, sha256, sha512, bcrypt, argon2i and argon2id are supported.');
        }
        return $normalizedHashAlgorithm;
    }

    public function getQueryGetPasswordHashForUser()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_PASSWORD_HASH_FOR_USER);
    }

    public function getQueryUserExists()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_USER_EXISTS);
    }

    public function getQueryGetUsers()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_USERS);
    }

    public function getQuerySetPasswordForUser()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_SET_PASSWORD_HASH_FOR_USER);
    }

    public function getQueryDeleteUser()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_DELETE_USER);
    }

    public function getQueryGetDisplayName()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_DISPLAY_NAME);
    }

    public function getQuerySetDisplayName()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_SET_DISPLAY_NAME);
    }

    public function getQueryCountUsers()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_COUNT_USERS);
    }

    public function getQueryGetHome()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_GET_HOME);
    }

    public function getQueryCreateUser()
    {
        return $this->getQueryStringOrFalse(self::CONFIG_KEY_CREATE_USER);
    }



    /**
     * Tries to read a config value and throws an exception if it is not set.
     * This is used for config keys that are mandatory.
     * @param $configKey string key name of configuration parameter
     * @return string|array the value of the configuration parameter, which also
     * can be an array (for queries).
     * @throws \UnexpectedValueException
     */
    private function getConfigValueOrThrowException($configKey)
    {
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
    private function getConfigValueOrDefaultValue($configKey, $defaultValue)
    {
        if (empty($this->appConfiguration[$configKey])) {
            $this->logger->debug('The config key ' . $configKey
                . ' is not set, defaulting to ' . $defaultValue . '.');
            return $defaultValue;
        } else {
            return $this->appConfiguration[$configKey];
        }
    }

    /**
     * Tries to read a config value and if it is set returns its value,
     * otherwise returns FALSE. This is used for optional configuration keys
     * where default values are not known, i.e. SQL queries.
     * @param $configKey string key name of configuration parameter
     * @return string|bool value of configuration parameter or false if it is
     * not set
     */
    private function getConfigValueOrFalse($configKey)
    {
        if (empty($this->appConfiguration[$configKey])) {
            return false;
        } else {
            return $this->appConfiguration[$configKey];
        }
    }

    private function getValueOrFalse($value)
    {
        return empty($value) ? false : $value;
    }

    /**
     * Tries to read a query value and if it is set returns its value,
     * otherwise returns FALSE.
     * @param $configKey string key name of configuration parameter
     * @return string|bool value of configuration parameter or false if it is
     * not set
     */
    private function getQueryStringOrFalse($configKey)
    {
        $queryArray = $this->getConfigValueOrThrowException(self::CONFIG_KEY_QUERIES);
        return $this->getValueOrFalse($queryArray[$configKey] ?? false);
    }

    /**
     * Checks whether hash algorithm is supported for writing.
     * @param $hashAlgorithm string hash algorithm descriptor to check
     * @return bool whether hash algorithm is supported
     */
    private function hashAlgorithmIsSupported($hashAlgorithm)
    {
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
    private function normalize($string)
    {
        return strtolower(preg_replace("/[-_]/", "", $string));
    }

}
