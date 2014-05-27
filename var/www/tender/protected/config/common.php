<?php 
define('GENEXCEL_RET_SUCCESS', 3 );
define('GENEXCEL_RET_ERR_NO_FILE', 2 );
define('XLS_STAT_IN_QUEUE', 0 );
define('XLS_STAT_BEGIN_PROCESSING', 1 );
define('XLS_STAT_CREATE_XLS', 2 );
define('XLS_STAT_SUCCESS', 3 );
define('XLS_STAT_ERR_NO_FILE', 10 );
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'../extensions/Kint/Kint.php';
class params {
	public static $params = array(
		// this is used in contact page
		'adminEmail'=>'zubran@gmail.com',
		'databus' => array (
			'redis' => array( 'host' => 'localhost', 'port' => 6379 ),
		),
		'mongodb' => array(
		    'class' => 'EMongoClient',
		    'db' => 'tender',
			'RP' => array('RP_PRIMARY', array()),
		),
		// 		Наименование - поле name
		// 		ISBN (Артикул) - поле isbnn
		// 		Автор - поле price_authors
		// 		Серия(Тематика) - поле serie
		// 		Стандарт (кол. штук в упаковке) - на данный момент отсутствует в МК, будет добавлено
		// 		Издательство (производитель) - поле publi
		// 		Кол страниц - поле qtypg
		// 		Суммарный тираж, шт. - поле edful
		// 		ЦенаОпт (наша прайсовая цена) - поле price
		// 		ISBN13 - поле ISBN
		// 		Штрихкод EAN13  - поле ISBN, только цифры без тире
		// 		Вес единицы в гр. - поле brgew
		// 		Год издания в формате (2012) - только год из поля ldate_d
		// 		Высота ед. - поле height
		// 		Ширина ед. - поле width
		// 		Дата первого тиража - поле sdate_d
		// 		Дата последнего тиража - поле ldate_d
		// 		Код тематики - поле sbjct
		// 		Аннотация  - поле detail_text
		
		'xls_fields' => array(
				'qtypg' 		=> array( 'type' => 'int', 'col' => 'K' ),
				'edful' 		=> array( 'type' => 'int', 'col' => 'L' ),
				'price' 		=> array( 'type' => 'float', 'col' => 'Q' ),
				'brgew' 		=> array( 'type' => 'float', 'col' => 'V' ),
				'width' 		=> array( 'type' => 'float', 'col' => 'AB' ),
				'height' 		=> array( 'type' => 'float', 'col' => 'AC' ),
				'remainder' 		=> array( 'type' => 'float'),
				'name' 					=> array( 'type' => 'string', 'col' => 'C' ),
				'isbnn' 				=> array( 'type' => 'string', 'col' => 'D' ),
				'price_authors' 		=> array( 'type' => 'string', 'col' => 'E' ),
				'serie' 				=> array( 'type' => 'mkdict', 'col' => 'H', 'id' => 'xml_id', 'group' => 'series', 'item' => 'serie', 'value' => 'name' ),
				'publi' 				=> array( 'type' => 'mkdict', 'col' => 'J', 'id' => 'xml_id', 'group' => 'publishers', 'item' => 'publisher', 'value' => 'name' ),
				'sdate_d' 				=> array( 'type' => 'string', 'col' => 'AF' ),
				'ldate_d' 				=> array( 'type' => 'string', 'col' => 'AG' ),
				'sbjct' 				=> array( 'type' => 'mkdict', 'col' => 'AH','id' => 'xml_id', 'group' => 'subjects', 'item' => 'subject', 'value' => 'name' ),
				'detail_text' 			=> array( 'type' => 'string', 'col' => 'AI' ),
		),
		'xls_fields_calc' => array(
				'isbn13' 				=> array( 'type' => 'string', 'col' => 'S' ),
				'ean13' 				=> array( 'type' => 'int', 'col' => 'T' ),
				'year_pub'				=> array( 'type' => 'int', 'col' => 'Z' ),
				
		),
		'xls_files' => array(
				'uploaded' 		=> array( 'path' => '/var/www/files/1.uploaded' ),
				'processing'	=> array( 'path' => '/var/www/files/2.processing' ),
				'done'			=> array( 'path' => '/var/www/files/3.done' ),
				'template'		=> array( 'fullname' => '/var/www/files/tmpl_out.xls' ),
		),
		'googleServerKey' => array (
				'AIzaSyD2WHSq-HLJr5-ScbbeisJ6Qj5OXo1co_w', 	#t.b.e
				'AIzaSyAsWocXh-68nLo7Ru9M0sdSWYbyynRHA3c',	#zubran@gmail.com
		),
		'GBDayLimit'	=> 1000,
		'BooksCatalog'	=> array(
// 				'ast' => array( 'url' => "http://partners.eksmo.ru/wservices/xml/?action=products_ast" ),
// 				'eksmo' => array( 'url' => "http://partners.eksmo.ru/wservices/xml/?action=products_full" ),
				'ast' => array( 'url' => "https://partners.eksmo.ru/wservices/xml/v1/?action=products_ast_full&key=41e5d8fc6146376a6d62f827bc540626" ),
				'eksmo' => array( 'url' => "https://partners.eksmo.ru/wservices/xml/v1/?action=products_full&key=41e5d8fc6146376a6d62f827bc540626" ),				
		)
		
	);
}
if (is_file(__DIR__.'/common_local.php'))
	require_once(__DIR__.'/common_local.php');
?>