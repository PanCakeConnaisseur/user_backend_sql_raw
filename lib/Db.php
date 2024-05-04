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

use OCA\UserBackendSqlRaw\Config;
use Psr\Log\LoggerInterface;
use \PDO;

/**
 * Class Db combines common methods of the db access and db handle (PDO)
 * creation.
 * @package OCA\UserBackendSqlRaw
 */
class Db
{
    /** @var Config  */
    protected $config;

    /** @var PDO */
    private $dbHandle;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns a db handle (PDO object).
     * @return PDO a PDO object that is used for database access
     */
    public function getDbHandle()
    {
        if (is_null($this->dbHandle)) {
            $this->dbHandle = $this->createDbHandle();
            // Some methods of the backend are called by Nextcloud in a way that
            // suppresses exceptions, probably to avoid leaking passwords to log
            // files. Therefore it is not necessary to change PDO::ATTR_ERRMODE for
            // these manually. These methods are (as of Nextcloud 13.0.1):
            // createUser(). But not setPassword(). Because checkPassword() only
            // retrieves the hash it does not suffer from this problem at all.
        }
        // Some methods change the error mode, therefore it needs to be reset to
        // the default value.
        $this->dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $this->dbHandle;
    }

    /**
     * Returns a new PDO db handle
     * @return PDO a new PDO object
     */
    protected function createDbHandle()
    {
        $username = $this->config->getDbUser();
        $password = $this->config->getDbPassword();
        $dsn = $this->config->getDsn();

        // The PDO constructor does not seem to be able to handle parameters
        // with `false` values. Therefore, feeding it here manually all options.
        if ($username and $password) {
            return new PDO(dsn: $dsn, username: $username, password: $password);
        } elseif ($username and !$password) {
            return new PDO(dsn: $dsn, username: $username);
        } elseif (!$username and $password) {
            return new PDO(dsn: $dsn, password: $password);
        } else {
            return new PDO($dsn);
        }
    }
}
