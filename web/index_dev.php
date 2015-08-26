<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

$application = require_once __DIR__.'/../app/bootstrap.php';
$application->run();
