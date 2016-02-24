<?php

define('LIB', dirname(__FILE__) . '/../lib');
define('CONF', dirname(__FILE__) . '/../conf');

require LIB . '/console.php';
require LIB . '/HttpServer.php';

$config = parse_ini_file(CONF . '/application.ini', true);

$httpServer = new HttpServer($config['server-setting']);

$httpServer->run();


