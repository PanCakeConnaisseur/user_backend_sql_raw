<?php

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__.'/../../../lib/base.php';

\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

OC_Hook::clear();
