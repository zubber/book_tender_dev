#!/usr/bin/php -q
<?php

require_once dirname(__FILE__).'/../TenderDaemon.php';
require_once "QueueManager.php";

$d = new TenderDaemon('QueueManager', array(
	'appName' => 'queue_manager',
	'appDescription' => 'Manages queue manager for tender progect',
	'authorName' => 'Malyutin Vyacheslav',
	'authorEmail' => 'zubran@gmail.com',
));
$d->run($argv);
	
?>