<?php

namespace OCA\UserBackendSqlRaw\AppInfo;

use \OCP\AppFramework\App;
use \OCA\UserBackendSqlRaw\UserBackend;
use \OCA\UserBackendSqlRaw\Db;

class Application extends App {

	public function __construct(array $urlParams = array()) {
		parent::__construct('user_backend_sql_raw', $urlParams);

		$config = $this->getContainer()->getServer()->getConfig();
		\OC::$server->getUserManager()->registerBackend(new UserBackend($config, new Db($config)));
	}
}