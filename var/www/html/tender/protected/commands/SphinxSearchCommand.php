<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

define( "RET_ERR_JSON_ENCODE", 2 );
define( "RET_ERR_DAY_LIMIT", 3 );

class SphinxSearchCommand extends CConsoleCommand
{
	private $_bookKeys 			= array( 'authors', 'publishedDate', 'publisher', 'industryIdentifiers', 'pageCount', 'printType', 'title', 'canonicalVolumeLink');
	private $_config 				= false;
	private $_bus					= false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 
	
	public function getSphinxQuery($sQuery)
	{
		$aRequestString=preg_split('/[\s,-]+/', $sQuery, 5);
		$wc = count($aRequestString);
		$aKeyword = array();
		foreach ($aRequestString as $sValue)
		{
			if (strlen($sValue)>3)
			{
				$aKeyword[] .= $sValue;
				#$aKeyword[] .= "(".$sValue." | *".$sValue."*)";
				
			}
		}
		if ( $wc > 2 )
			$sSphinxKeyword = '@(name,author) "' . implode(" ", $aKeyword) . '"/' . ( $wc - 2 );
		else
			$sSphinxKeyword = '@(name,author) ' . implode(" ", $aKeyword);
		return $sSphinxKeyword;
	}

	public function run($args)
	{
		$is_debug = 1;
		$arg_data = json_decode($args[0],  true);
		$seq_ids = array();
		
		// Создаем массив с ошибками парсинга json
		$constants = get_defined_constants(true);
			
		$json_errors = array();
		foreach ($constants["json"] as $name => $value) {
			if (!strncmp($name, "JSON_ERROR_", 11)) {
				$json_errors[$value] = $name;
			}
		}

		
		$le = json_last_error();
		if ( $le )
		{
			echo 'Last error: ', $json_errors[$le], PHP_EOL, PHP_EOL;
			return RET_ERR_JSON_ENCODE;
		}
		
		
		$book_id = $arg_data['b'];
		$book_name = preg_replace ('(«|»)',"",trim($arg_data['n']));
		$book_name = preg_replace ("/![^\w\d\s]*!/","",$book_name);
		
		$wc = count(preg_split( "/[\s.,;]+/", $arg_data['n'] ));		#var_dump($wc); die;
		
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_bc = $mdb_conn->tender->books_catalog;
		$query = array( "name" => $book_name );
		
		$m_cur = $mdb_bc->findOne($query);
		if ( $m_cur )
		{
			$data = array(
					"search_results" => array (
							"matches" 	=> array(array(
									"_seq_id" => $m_cur['_seq_id'],
									"percentage" => 1,
									"weight" => 10,
							)),
							"best_percentage" => 1,
							"count"		=> 1
					)
			);
			array_push( $seq_ids, (int)$m_cur['_seq_id'] );
			$found_flag = 2;
			$status = 11;
		}
		else {
			$s = new SphinxClient;
			$s->setServer("127.0.0.1", 9312);
			$s->setMaxQueryTime(30);
			$s->SetLimits(0, 10, 10);
// 			$s->setMatchMode(SPH_MATCH_ANY);
// 			$result = $s->query($book_name);
			$s->setMatchMode(SPH_MATCH_EXTENDED);
			$result = $s->query($this->getSphinxQuery($book_name));
		
			
			
			if ( $result === false ) {
				echo "Query failed: " . $s->GetLastError() . ".\n";
				return;
			}
			else {
				if ( $s->GetLastWarning() ) {
					echo "WARNING: " . $s->GetLastWarning() . "";
				}
				$status = 10;
				$found_flag = 0;

				if ( ! empty($result["matches"]) ) {
					//Сортируем по весу
					$matches = array();
					 //не найден 
					#{Sphinx Weight} / {Number of Words in Search Query} / {Number of Fields in Sphinx			Database} / 12
					foreach ( $result["matches"] as $doc => $docinfo )
					{
						$percentage = round( $docinfo['weight'] / $wc / 2 / 2, 2 );
						if ( $percentage > 1 ) $percentage = 1;
						if ( $percentage >= 0.9 )
							$found_flag = 1; //Точное совпадение
						elseif ( $percentage >=  0.66 )
							$found_flag = 2; //Не точное совпадение
						else { //Плохое совпадение, выходим
							$data = array( "search_results"	=> array( "count" => 0 ) );
							break;
						}
							
	 					array_push( $matches, array_merge( array( "_seq_id" => (int)$doc, "percentage" => $percentage), $docinfo ) );
	 					array_push( $seq_ids, (int)$doc );
					}
					
					if ( count( $matches ))
					{
						usort($matches,"sort_by_weight");
							
						$data = array(
								"search_results" => array (
										"matches" 	=> $matches,
										"best_percentage" => $matches[0]["percentage"],
										"count"		=> count($matches)
								)
						);
						$status = 11;
					}
				}
				else			
					$data = array( "search_results"	=> array( "count" => 0 ) );
			}
		}
		

		if ($is_debug == 1) {
			$mdb_bc = $mdb_conn->tender->books_catalog;
			$query = array( "_seq_id" => array( '$in' => $seq_ids ) );
			$projection = array( "name" => true, "xml_id" => true, "_seq_id" => true );
			$m_cur = $mdb_bc->find($query, $projection);
			var_dump($data);
			#foreach( $m_cur as $find_book)					var_dump($find_book);
		}
		
		$mdb_books = $mdb_conn->tender->books;
		$mdb_books->update( array("b_id" => (int)$book_id), array('$set' => $data ) );
		$arg_data['s'] = $status;
		$arg_data['f'] = $found_flag;
		$this->_bus->triggerSphinxCompleteRequest( $arg_data );
		return RET_OK;
	}
}

function sort_by_weight($a, $b)
{
	if ($a['weight'] == $b['weight']) {
		return 0;
	}
	return ($a['weight'] > $b['weight']) ? -1 : 1;
}