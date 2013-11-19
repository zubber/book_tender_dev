<?php
require_once('common.php');
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Console configuration',

	// preloading 'log' component
	'preload'=>array('log'),
	'import'=>array(
			'application.models.XlsFile'
	),
	// application components
	'components'=>array(
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=tender',
			'emulatePrepare' => true,
			'username' => 'www',
			'password' => '12345678',
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
	),
	'params' => $commonParams
);