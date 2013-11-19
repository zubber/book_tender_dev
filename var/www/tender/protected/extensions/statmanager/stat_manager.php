#!/usr/bin/php -q
<?php

require_once dirname(__FILE__).'/../TenderDaemon.php';
require_once "StatManager.php";

$d = new TenderDaemon('StatManager', array(
	'appName' => 'stat_manager',
	'appDescription' => 'Collects statistics to mongodb for tender progect',
	'authorName' => 'Malyutin Vyacheslav',
	'authorEmail' => 'zubran@gmail.com',
));
$d->run($argv);
	
?>