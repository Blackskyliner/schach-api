<?php

namespace Htwdd\Chessapi;

use Silex\Application;

define('APP_ROOT', __DIR__);
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', __DIR__.'/../web');
}
define('VENDOR_ROOT', __DIR__.'/../vendor');
define('DATA_ROOT', __DIR__.'/../data');

if (!file_exists(VENDOR_ROOT)) {
    error_log('Bitte installieren Sie alle Abhängigkeiten über composer.');
    exit;
}

// Hole den Composer autoloader.
$autoloader = require_once VENDOR_ROOT.'/autoload.php';

$application = new Application(
    require_once 'configuration.php'
);

$application->register(new ApiServiceProvider());

return $application;
