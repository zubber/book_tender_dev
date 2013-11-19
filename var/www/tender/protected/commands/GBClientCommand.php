<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

define( "RET_ERR_JSON_ENCODE", 2 );
define( "RET_ERR_DAY_LIMIT", 3 );

class GBClientCommand extends CConsoleCommand
{
	private $_bookKeys 			= array( 'authors', 'publishedDate', 'publisher', 'industryIdentifiers', 'pageCount', 'printType', 'title', 'canonicalVolumeLink');
	private $_config 				= false;
	private $_bus					= false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 

	private function getAPIKey()
	{
		return Yii::app()->params['googleServerKey'][0];
	}
	
	/**
	 * ДЕлает запрос к Google.books и возвращает массив со статусом ответа, строкой запроса и результатом из items 
	 * @param string $bookName имя книги 
	 * @param string $author автор
	 * @return multitype:string multitype: |multitype:string
	 */
	
	public function getBookData($bookName, $author = false) {
		#экспериментируем со строкой запроса
		#$request = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($bookName) . '&key=' . $this->getAPIKey();
		$request = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($bookName);
// 		if ( $author )
// 			$request .= '+inauthor:' . urlencode($author);
		$request .= '&key=' . $this->getAPIKey();
		
#		$json = file_get_contents( $request );

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		$json = curl_exec($ch);
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close($ch);

		#На всякий случай проверим, вдруг 403
		if ( (int)$code != 200 )
		{
			if ( (int)$code == 403 )
			{
				return RET_ERR_DAY_LIMIT;
			}
		}
		#После запроса к гуглу создаем событие для stat_manager, дабы увеличить счетчик запросов на 1
		$this->_bus->triggerGBRequest();
		
		$response = json_decode($json, true);
		$totalItems = $response['totalItems'];
		$matchingItems = array();
		$deferredItems = array();		#Сюда складываем книги, у которых в поисковом отсутствовал издатель, будем запрашивать позже по selfLink.
		
		if ( $totalItems > 0 )
		{
			foreach ( $response['items'] as $item )
			{
//				if ( $item['volumeInfo']['title'] == $bookName )	#на всякий случай - ищем только точное название книги
				if ( strpos( strtolower($bookName), strtolower($item['volumeInfo']['title'] ) ) !== false )
				{
					$book = array();
					foreach ( $this->_bookKeys as $bookKey )
						if ( isset( $item['volumeInfo'][$bookKey] ))
							$book[$bookKey] = $item['volumeInfo'][$bookKey];

					$book['selfLink'] = $item['selfLink'];
					$book['g_id']	= $item['id'];
						
//					if ( isset( $book['publisher'] ) )
					{
						array_push( $matchingItems, $book );
					}
// 					else 
// 					{
// 						array_push( $deferredItems, $book );	#сделаем позже индивидуальный запрос по selfLink 
// 					}
				}
			}
				
			if ( count( $deferredItems > 0 ) )
			{
				foreach ( $deferredItems as $item )
				{
					$json = file_get_contents( $item['selfLink'] );
					$response = json_decode($json, true);
					if ( isset ($response['id']) && isset ($response['volumeInfo']) ) 
					{
						$book = array();
						foreach ( $this->_bookKeys as $bookKey )
							$book[$bookKey] = $response['volumeInfo'][$bookKey];
						if ( isset( $book['publisher'] ) )
						{
							array_push( $matchingItems, $book );
						}
					}
				}
			}
		}
		
		if ( count($matchingItems) == 1 )
		{
			return array (
					'status'	=> '11',
					'request'	=> $request,
					'items'	=> $matchingItems
			);
		}
		elseif ( count($matchingItems) > 1 )	
		{
			return array (
				'status'	=> '12',
				'request'	=> $request,
				'items'	=> $matchingItems
			);
		}
		else # нет результатов, статус 10
		{
			return array ( 'status'	=> '10', 'request'	=> $request );
		}
	}
	
	public function run($args)
	{
		// Создаем массив с ошибками.
		$constants = get_defined_constants(true);
		
		$json_errors = array();
		foreach ($constants["json"] as $name => $value) {
			if (!strncmp($name, "JSON_ERROR_", 11)) {
				$json_errors[$value] = $name;
			}
		}
		
		$arg_data = json_decode($args[0],  true);
		$le = json_last_error();
		if ( $le )
		{
			echo 'Last error: ', $json_errors[$le], PHP_EOL, PHP_EOL;
			return RET_ERR_JSON_ENCODE;
		}
	
		$book_id = $arg_data['b'];
		$book_name = $arg_data['n'];

		$data = $this->getBookData($book_name, $arg_data['a']);
		
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		if ( $data == RET_ERR_DAY_LIMIT )
		{
		
			$mdb = $mdb_conn->tender->qm;
			$command = "genexcel " . $args[0];
			$mdb->update( array( 'x' => (int)$arg_data['x'] ), array( '$addToSet' => array( 'deferred' => $command ) ));
			return RET_ERR_DAY_LIMIT;
		}
		else 
		{
		
	 		$mdb_books = $mdb_conn->tender->books;
	 		$mdb_books->update( array("b_id" => $book_id+0), array('$set' => $data ) );
		
	 		$arg_data['s'] = $data['status'];
	 		$this->_bus->triggerGBCompleteRequest( $arg_data );
			
			return RET_OK;
		}
	}
	
	private function log($msg)
	{
		echo "gbclient: $msg";
	}

}

?>