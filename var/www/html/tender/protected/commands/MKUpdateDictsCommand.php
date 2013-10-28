<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/Utils.php');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

class MKUpdateDictsCommand extends CConsoleCommand
{
	private $_uri		= 'http://partners.eksmo.ru/wservices/xml/?action=';
	private $_actions	= array(
		'serie'	=> array( 'id' => 'xml_id', 'group' => 'series', 'item' => 'serie' ),
		'publi'	=> array( 'id' => 'xml_id', 'group' => 'publishers', 'item' => 'publisher' ),
		'sbjct'	=> array( 'id' => 'xml_id', 'group' => 'subjects', 'item' => 'subject' ),
		'authr'	=> array( 'id' => 'xml_id', 'group' => 'authors', 'item' => 'author' ),
	);
	private $_bus		= false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	}
	
	public function run($args)
	{
		$action = $args[0];		
		$request = $this->_uri . $action;
		$collection = preg_match( '/action=([a-z]+)$/', $request, $matches );
		$mk_data = get_xml($request); 
		
		if ( isset($mk_data) && isset($mk_data->pages) && isset($mk_data->pages->all ) && $mk_data->pages->all > 0 )
		{
			$doc_count = 0;
			$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
			$mkc_name = "mk_$action"; 
			$mdc = $mdb_conn->tender->$mkc_name;
			$ap = $this->_actions[$action];
			$pages = $mk_data->pages->all;
			$item_count = $mk_data->pages->items;
			$cur_item_no = 1;
			_log('ok, there is results. Page count is : '.$pages );
			$cur_page = 1;
			do 
			{
				if ( $mk_data !== true )
				{			#	var_dump($mk_data);	return;				
					foreach ( $mk_data->$ap['group']->$ap['item'] as $item )
					{
						if ( isset( $item->name ) )
							$item->name = htmlspecialchars_decode($item->name, ENT_COMPAT | ENT_XML1 );
						$query	= array( $ap['id'] => (string)$item->$ap['id']);
						$doc	= $item;
						$cur_item_no++;
						_log('updating '.$cur_item_no .'/'.$item_count . ', pages ' . $cur_page . '/' . $pages);
						$mdc->update($query, $item, array( 'upsert' => 1 ));
					}
				}
				$cur_page++;
				$next_request = $request . '&page=' . $cur_page;
				$mk_data = get_xml($next_request);
			} while ( $cur_page <= $pages && $mk_data !== false );
		}
		
	}
}


//Что запрашиваем:
// 	1. Серия
// 	http://partners.eksmo.ru/wservices/xml/?action=serie, ключ xml_id, значение name
// 	2. Издательство
// 	http://partners.eksmo.ru/wservices/xml/?action=publi, ключ xml_id, значение name
// 	3. Тематика
// 	http://partners.eksmo.ru/wservices/xml/?action=sbjct, ключ xml_id, значение name

//Пример заголовка ответа:
//<result>
// 	<pages>
// 		<all>14</all>
// 		<current>1</current>
// 		<items>1350</items>
// 	</pages>
// <subjects>
// 	<subject>
// 		<name>Российская историческая литература</name>
// 		<xml_id>ast-9-f89a763b-1c58-11e2-818f-5ef3fc5021a7</xml_id>
// 		<guid>f89a763b-1c58-11e2-818f-5ef3fc5021a7</guid>
// 		<parent_guid>567585</parent_guid>
// 	</subject>
//	...
// </subjects>
//</result>
?>
	
