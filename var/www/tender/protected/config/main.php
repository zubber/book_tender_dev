<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

require_once('common.php');
Yii::setPathOfAlias('bootstrap', dirname(__FILE__).'/../extensions/bootstrap'); 
$params = array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'ИС Библиотека-Тендер',
	'language'=>'ru',
	// preloading 'log' component
	'preload'=>array('log','bootstrap','kint'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.extensions.MongoYii.*',
		'application.extensions.MongoYii.validators.*',
		'application.extensions.MongoYii.behaviors.*',
		'application.extensions.MongoYii.util.*'
	),
		
	'theme'=>'bootstrap',
	'modules'=>array(
		// uncomment the following to enable the Gii tool
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'123',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'=> false, #array('127.0.0.1','192.168.1.*','::1'),
			'generatorPaths'=>array(
					'bootstrap1.gii',
			),
		),
	),
	
	// application components
	'components'=>array(
		'mongodb' => params::$params['mongodb'],
		'authManager' => array(
		    'class' => 'application.extensions.MongoYii.util.EMongoAuthManager',
		),
		'session' => array(
		    'class' => 'application.extensions.MongoYii.util.EMongoSession',
		),
		'user' => array(
			'class' => 'EWebUser',
			'allowAutoLogin'=>true,
		),
		'kint' => array(
				'class' => 'ext.Kint.Kint',
		),
		'bootstrap'=>array(
				'class'=>'bootstrap.components.Bootstrap',
		),  
		// uncomment the following to enable URLs in path-format
		/*
		'urlManager'=>array(
			'urlFormat'=>'path',
			'rules'=>array(
				'<controller:\w+>/<id:\d+>'=>'<controller>/view',
				'<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		*/
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=tender',
			'emulatePrepare' => true,
			'username' => 'www',
			'password' => '',
			'charset' => 'utf8',
		),
		'errorHandler'=>array(
			// use 'site/error' action to display errors
			'errorAction'=>'site/error',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				// uncomment the following to show log messages on web pages
				/*
				array(
					'class'=>'CWebLogRoute',
				),
				*/
				array(
					'class'=>'CProfileLogRoute',
					'report'=>'summary',
				),
			),
		),
		'cache' => array(
				'class' => 'CApcCache',
		),
	),

	// application-level parameters that can be accessed
	// using Yii::app()->params['paramName']
	'params'=> params::$params,
);
// print_r($params);die;
return $params;