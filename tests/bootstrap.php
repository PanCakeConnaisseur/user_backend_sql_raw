<?php

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__.'/../../../lib/base.php';

\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

if (!class_exists('\PHPUnit\Framework\TestCase') &&
	class_exists('\PHPUnit_Framework_TestCase')) {
	class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
    require_once('../vendor/autoload.php');
}

OC_Hook::clear();
