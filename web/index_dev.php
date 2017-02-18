<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

$application = require __DIR__ . '/../app/bootstrap.php';

$application['debug'] = true;
$application->run();
