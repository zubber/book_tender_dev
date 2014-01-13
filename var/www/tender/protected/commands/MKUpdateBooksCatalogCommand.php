<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/Utils.php');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

class MKUpdateBooksCatalogCommand extends CConsoleCommand
{
	private $_bus = false;
	private $total_pages;
	function __construct() {
		$this->_bus = new DataBus(Yii::app()->params);
	}
	
	public function run($args) {
		$url = $args[0];
		$page = isset($args[1]) ? $args[1] : 1;
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );		
		$mdc = $mdb_conn->tender->books_catalog;
		
		do {
			$request = $url.'&page='.$page;
			try {
				$mk_data = get_xml($request);
			} catch (Exception $e) {
				_log('ERR IN XML: '.$request );
				continue;
			}
			
			$json_string = json_encode($mk_data);
			$mk_data = json_decode($json_string, TRUE);
				
// var_dump($mk_data);exit();
//var_dump(isset($mk_data) && isset($mk_data->pages) && isset($mk_data->pages->all ) && $mk_data->pages->all > 0);
			if ( isset($mk_data) && isset($mk_data["pages"]) && isset($mk_data["pages"]["all"] ) && $mk_data["pages"]["all"] > 0 ) {
				if ( !isset($this->total_pages) )
					$this->total_pages = $mk_data["pages"]["all"];
				if ( $page > $this->total_pages ) {
					_log('COMPLETE on' . $this->total_pages . ' page.' );
					exit();
				}
					
				foreach (  $mk_data["products"]["product"] as $book ) {
					$query = array( 'xml_id' => $book["xml_id"]);
					$old_book = $mdc->findOne($query);
					if ($old_book && $old_book["_seq_id"]) {
						$book['_seq_id'] = $old_book["_seq_id"];
 						$mdc->update($query,$book);
					} 
					else {
						$seq = $mdb_conn->tender->sequences->findAndModify(array('_id'=>'book_id'),array('$inc'=>array('seq'=>1)));
						$book['_seq_id'] = $seq['seq'];
 						$mdc->insert($book);
 						_log("NEW: '" . $book["name"] . "' with seq=" . $seq['seq']);
//  						var_dump($book);
					}
				}
// 				if ( $page > 10 )				exit();
 				$page += 1;
			}
			else {
				_log( 'INCORRECT ARRAY:  ' . print_r($mk_data)); 
				exit();
			}
		} while (1);
	}
}


//Что запрашиваем:
//ast_url = "http://partners.eksmo.ru/wservices/xml/?action=products_ast"
//eksmo_url = "http://partners.eksmo.ru/wservices/xml/?action=products_full"
?>
	
