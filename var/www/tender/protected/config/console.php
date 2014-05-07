<?php
require_once('common.php');
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Console configuration',
	'language'=>'ru',
	// preloading 'log' component
	'preload'=>array('log'),
	'import'=>array(
		'application.models.XlsFile',
		'application.components.*',
		'application.extensions.MongoYii.*',
		'application.extensions.MongoYii.validators.*',
		'application.extensions.MongoYii.behaviors.*',
		'application.extensions.MongoYii.util.*'
	),
	// application components
	'components'=>array(
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=tender',
			'emulatePrepare' => true,
			'username' => 'www',
			'password' => '',
			'charset' => 'utf8',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
			),
		),
		'cache' => array(
				'class' => 'CApcCache',
		),
	),
	'params' => params::$params
);