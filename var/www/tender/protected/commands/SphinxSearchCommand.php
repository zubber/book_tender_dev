<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

define( "RET_ERR_JSON_ENCODE", 2 );
define( "RET_ERR_DAY_LIMIT", 3 );

define( "SPH_QUERY_NAME_AUTHOR", 1 );
define( "SPH_QUERY_NAME", 2 );

define( "SEARCH_QUALITY_EXCELLENT", 2 );
define( "SEARCH_QUALITY_GOOD", 1 );
define( "SEARCH_QUALITY_POOR", 0 );
define( "SEARCH_QUALITY_NOT_FOUND", 0 );

class SphinxSearchCommand extends TenderConsoleCommand
{
	private $_bookKeys 			= array( 'authors', 'publishedDate', 'publisher', 'industryIdentifiers', 'pageCount', 'printType', 'title', 'canonicalVolumeLink');
	private $_config 				= false;
	private $_bus					= false;
	
	private $_sph = array();
	private $_sv = false;
	private $_matches = array();	
	private $is_debug = 0;
	private $current_book = false;
	private $current_author = false;
	private $book_id = false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 
	
	public function preparePhrase($str)
	{
		$str_out = trim($str);
		$str_out = preg_replace ('(«|»)',"",$str_out);
		$str_out = str_replace(array('/','\\', '.'),' ', $str_out);
 		$str_out = preg_replace('~[^\p{L}\p{N}\p{Z}]++~u', ' ', $str_out);
		$str_out = trim($str_out);
		return $str_out;
	}
	
	public function prepareAuthor($str)
	{
		$str_out = $str;
		//Наша цель - оставить только фамилии и попытаться найти по ним в монго. Удалим одинокие буквы ( это сокращенные имя и отчество ).
// 		$str_out = preg_replace( '/[.,]/', ' ', $str );
		$str_out = str_replace( array( '.',',' ), ' ', $str );
		$str_out = mb_ereg_replace( '\s([А-Я]{1})', '', $str_out ); //Попытка убрать отчества. Однако есть например два Гумилева, Николай и Лев.
		return trim($str_out);
	}
	
	public function createSphinxQueries($sTitle, $sAuthors)
	{
		$aT=preg_split('/[\s,-]+/', $sTitle );
		$wc = count($aT);
		$sAuthors = trim( $sAuthors );
		$aA = array();
		if ( $sAuthors )
		{
			$aA=preg_split('/[\s,-]+/', $sAuthors );
			$this->log('Authors to search in sphinx: ' . implode( '|', $aA ) );
			
			$wc += count($aA);
		}
		$aKeywords = array_merge($aT,$aA);
		
		//Первый уровень поиска, в порядке ухудшения точности
		//1. Точное совпадение по поле_автор=автор и поле_название=название
		//2. Поиск автор + название в обоих полях
		//3. Поиск в поле поле_название фраз, построенных путем прибавления к первому слову из навзания последующих, по-очереди + поле_автор=автор 
		//4. Поиск в поле поле_названиеи и поле_автор фраз, построенных путем прибавления к первому слову из навзания последующих, по-очереди
		//5. Поиск в поле_название всех слов через ИЛИ 
		$q = '@name ' . implode(" ", $aT);
		if( $sAuthors ) 
			$q .= ' @author ' . implode(" ", $aA);
		$this->_sph[] = array( 's' => 1, 'q' =>  $q, 'wc' => $wc );
		
		$this->_sph[] = array( 's' => 2, 'q' => '@(name,author) ' . implode(" ", $aKeywords), 'wc' => $wc );
		
// 		$wc = 0;
// 		$aKeywords = array(); 
// 		foreach ($aT as $sValue)
// 		{
// 			$aKeywords[] .= $sValue;
// 			$wc++;		
// 		}
// 		$this->_sph[] = array( 's' => 2, 'q' => '@(name) ' . implode(" ", $aKeywords), 'wc' => $wc );
		
		$wc = count($aT);
		if ( $wc >= 2 )
		{
			$i = 0;
			for( $i = 2; $i <= $wc; $i++ ) {
				$q = '@(name) "' . implode( " ", array_slice($aT, 0, $i) ) . '"';
				if( $sAuthors )
					$q .= ' @author ' . implode(" ", $aA);
 				$this->_sph[] = array( 's' => 3, 'q' => $q, 'wc' => $i, 'match' => false );
 				$this->_sph[] = array( 's' => 4, 'q' => '@(name,author) ' . implode( " ", array_slice($aKeywords, 0, $i) ), 'wc' => $i, 'match' => false );
			}
		}
		$q = '@name ' . implode("|", $aT);
		if( $sAuthors )
			$q .= ' @author ' . implode("|", $aA);
		$this->_sph[] = array( 's' => 5, 'q' => $q, 'wc' => $wc );
		
		return true;
	}
	
	public function makeSphinxQueries($book_name, $authors)
	{
$this->beginProfile('searchInSphinxPrepare');
		$s = new SphinxClient;
		$s->setServer("127.0.0.1", 9312);
		$s->setMaxQueryTime(300);
		$s->SetLimits(0, 10, 10);
		$s->setMatchMode(SPH_MATCH_EXTENDED);
		$this->createSphinxQueries($book_name, $authors);
		$s->setFieldWeights ( array( 'name' => 1, 'author' => 2 ) );
		$index = 0;
		$result = array();
		$max = count($this->_sph);
		$is_match = false;
$this->endProfile('searchInSphinxPrepare');
		do {
			$this->_sv = &$this->_sph[$index];
			$this->endProfile('searchInSphinxBegin');
$this->beginProfile('searchInSphinxQuery');
			$result = $s->query($this->_sv['q']);
$this->endProfile('searchInSphinxQuery');
			if ( $result === false ) {
				print_r( "ERROR Query failed: " . $s->GetLastError() . ".\n" . "Query: " . $this->_sv['q'] . "\n\r");
				exit();
			}
			
			if ( $s->GetLastWarning() ) 
				echo "WARNING: " . $s->GetLastWarning() . "";
			
			if ($result['total_found'] > 0 )
			{
				$is_match = true;
				foreach ( $result['matches'] as $_seq_id => $book )
				{
					if ( !isset( $this->_matches[$_seq_id] ) || $book['weight'] > $this->_matches[$_seq_id]['weight'] )
						$this->_matches[$_seq_id] = array_merge( $book, array('_q' => $this->_sv['q']) );
				}
			}
			$index++;
		} while( $index < $max );

		return $is_match;
	}
	
	public function run($args)
	{
$this->beginProfile('total');
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
$this->beginProfile('preparePhrase');
		$book_id = $arg_data['b'];
		if ( $arg_data['n'] == $arg_data['a'] )
			$authors = '';
		else
			$authors = $this->preparePhrase($arg_data['a']);
		$book_name = $this->preparePhrase($arg_data['n']);
$this->endProfile('preparePhrase');
		$this->current_book = $book_name; //делается для журналирования
		$this->current_author = $authors;
		$this->book_id = $arg_data['b'];
$this->log( 'Begin book ' . $book_name . ". Author:" . $authors);
	
		$wc = count(preg_split( "/[\s.,;]+/", $arg_data['n'] ));		#var_dump($wc); die;
$this->beginProfile('searchBookAlreadyInMongo');		
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_bc = $mdb_conn->tender->books_catalog;
		$mdb_a = $mdb_conn->tender->mk_authr;
		$query = array( "name" => $book_name ); //TODO: поиск по автору
		$m_cur = $mdb_bc->find($query);
		$m_book = false;
		$matches = array();
		
		//пробуем точное совпадение
		$exact_query = array('name' => trim($book_name));
		if ( $authors ) 
			$exact_query['price_authors'] = $authors;
		$m_book = $mdb_bc->findOne($exact_query);		
$this->endProfile('searchBookAlreadyInMongo');
		
		if ( $m_book )
		{
			$matches = array(array(
					"_seq_id" => $m_book['_seq_id'],
					"percentage" => 1,
					"weight" => 10,
			));
			$data = array(
					"search_results" => array (
							"matches" 	=> $matches,
							"best_percentage" => 1,
							"count"		=> 1
					)
			);
			array_push( $seq_ids, (int)$m_book['_seq_id'] );
			array_push( $matches, array_merge( array( "_seq_id" => (int)$m_book['_seq_id'], "percentage" => 1) ) );
			$found_flag = SEARCH_QUALITY_EXCELLENT;
			$status = 11;
		}
		else {
$this->beginProfile('searchInSphinxAllQueries');
			$status = 10;
			$mdb_bc = $mdb_conn->tender->books_catalog;
			$found_flag = SEARCH_QUALITY_NOT_FOUND;
			if ( $this->makeSphinxQueries($book_name, $authors) ) {
				
				foreach ( $this->_matches as $seq_id => $docinfo )
				{
					//контрольная проверка - есть ли найденное название в запросе
					$query = array( "_seq_id" => $seq_id );
					$projection = array( "name" => true, "price_authors" => true );
					$m_book = $mdb_bc->findOne($query, $projection);
					if ( strpos($arg_data['n'], $m_book['name']) === false ) continue;
					
					//ок, книга подходит. Проверим вхождение авторов найденной книги в исходную строку авторов, если нет совпадения - результат неточный
					$is_authors_found = true;
					if ( !empty( $arg_data['a'] ) && $arg_data['a'] != $arg_data['n'] )
					{
// 						var_dump($m_book);
						$authors_reverse = preg_split( '/[\s,-]+/', $this->prepareAuthor($m_book['price_authors']) );
						foreach( $authors_reverse as $author )
						{
							$this->log( 'Search in authors for "'. $author. '"');
							if ( strpos( $arg_data['a'], $author ) === false )
							{
								$is_authors_found = false;
								break;
							}
						}						
					}
					if ( $is_authors_found ) $this->log( 'Authors found.' );
					
					#{Sphinx Weight} / {Number of Words in Search Query} / {Number of Fields in Sphinx Database} / 12
					$percentage = round( $docinfo['weight'] / $this->_sv['wc'] / 2 / 128, 2 );
 					if ( $percentage > 1 ) $percentage = 1;
 					$this->log('percentage: '. $percentage);
					if ( $percentage >= 0.9 && $is_authors_found )
						$found_flag = SEARCH_QUALITY_EXCELLENT; //Точное совпадение
					elseif ( $percentage >=  0.1 && $is_authors_found )
						$found_flag = SEARCH_QUALITY_GOOD; //Не точное совпадение
					else { //Плохое совпадение, выходим
						$data = array( "search_results"	=> array( "count" => 0 ) );
						break;
					}
						
 					array_push( $matches, array_merge( array( "_seq_id" => (int)$seq_id, "percentage" => $percentage), $docinfo ) );
 					array_push( $seq_ids, (int)$seq_id );
				}
				
				if ( count( $matches ))
				{
					//Сортируем по весу
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
$this->endProfile('searchInSphinxAllQueries');
		}
		
		if ( !count( $matches ) )
			$data = array( "search_results"	=> array( "count" => 0 ) );
		
		

		if ($this->is_debug == 1) {
			if ( count( $matches ) )
			{
				$mdb_bc = $mdb_conn->tender->books_catalog;
				$query = array( "_seq_id" => array( '$in' => $seq_ids ) );
				$projection = array( "name" => true, "price_authors" => true, "xml_id" => true, "_seq_id" => true );
				$m_cur = $mdb_bc->find($query, $projection);
				
				foreach( $m_cur as $find_book)
					for( $m = 0; $m < count($matches); $m++ )
						if ( $matches[$m]['_seq_id'] == $find_book['_seq_id'] )
							$matches[$m] = array_merge( $find_book,$matches[$m]);
				var_dump($matches);
			}
			else 
				$this->log("no matches found.");

// 			var_dump($this->_sph);
			$this->afterAction();
			exit();
		}
		
		$mdb_books = $mdb_conn->tender->books;
		$mdb_books->update( array("b_id" => (int)$book_id), array('$set' => $data ) );
		$arg_data['s'] = $status;
		$arg_data['f'] = $found_flag;
		$this->_bus->triggerSphinxCompleteRequest( $arg_data );
$this->endProfile('total');
		$this->afterAction();
		return RET_OK;
	}
	
// 	public function log($msg)
// 	{
// 		if ( $this->is_debug > 0 )	print($this->book_id.": {$msg}\n");
// 	}
}

function sort_by_weight($a, $b)
{
	if ($a['weight'] == $b['weight']) {
		return 0;
	}
	return ($a['weight'] > $b['weight']) ? -1 : 1;
}