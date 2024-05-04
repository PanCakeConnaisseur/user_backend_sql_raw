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

use OCA\UserBackendSqlRaw\Db;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Container\ContainerInterface;
use \OCP\AppFramework\App;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'user_backend_sql_raw';

    public function __construct(array $urlParams = array())
    {
        parent::__construct(Application::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
    }

    public function boot(IBootContext $context): void
    {
        $userBackendSqlRaw = $context->getAppContainer()->get(\OCA\UserBackendSqlRaw\UserBackend::class);
        $userManager = $context->getAppContainer()->get('OCP\IUserManager');
        $userManager->registerBackend($userBackendSqlRaw);
    }
}
