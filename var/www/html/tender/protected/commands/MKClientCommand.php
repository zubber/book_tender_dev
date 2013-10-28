<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');
require_once(dirname(__FILE__).'/../extensions/Utils.php');
require_once(dirname(__FILE__).'/../../../../ISBN-Tools/isbn_tools.php');

class MKClientCommand extends CConsoleCommand
{
	private $_keysInt 			= array( 'qtypg', 'edful');
	private $_keysFloat			= array( 'price','brgew','height','width','reminder');
	private $_keysString		= array( 'name', 'isbnn', 'price_authors', 'serie', 'publi','ldate_d','sdate_d','sbjct','detail_text');
	private $_config 			= false;
	private $_bus				= false;
	
	
// 	Наименование - поле name
// 	ISBN (Артикул) - поле isbnn
// 	Автор - поле price_authors
// 	Серия(Тематика) - поле serie
// 	Стандарт (кол. штук в упаковке) - на данный момент отсутствует в МК, будет добавлено
// 	Издательство (производитель) - поле publi
// 	Кол страниц - поле qtypg
// 	Суммарный тираж, шт. - поле edful
// 	ЦенаОпт (наша прайсовая цена) - поле price
// 	*ISBN13 - поле ISBN
// 	*Штрихкод EAN13  - поле ISBN, только цифры без тире
// 	Вес единицы в гр. - поле brgew
// 	Год издания в формате (2012) - только год из поля ldate_d
// 	Высота ед. - поле height
// 	Ширина ед. - поле width
// 	Дата первого тиража - поле sdate_d
// 	Дата последнего тиража - поле ldate_d
// 	Код тематики - поле sbjct
// 	Аннотация  - поле detail_text
	
	
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 

	private function getAPIKey()
	{
		return Yii::app()->params['googleServerKey'][0];
	}
	
	public function run($args)
	{
		$arg_data = json_decode($args[0], true);
		$book_id = $arg_data['b'];

		#try 
		{
		
			if ( $arg_data['s'] == 10 ) #по книге из GB нет результатов
			{
				$this->_bus->triggerMKCompleteRequest( array('b' => $book_id, 'x' => $arg_data['x'], 's' => 21 ));
				$this->_bus->triggerMKCompleteAllRequests( array( 'b' => $book_id, 'x' => $arg_data['x'], 's' => 21 ));
				return RET_OK;
			}
	
			$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
			$mdb_books = $mdb_conn->tender->books;
			$book_data = $mdb_books->findOne( array("b_id" => $book_id+0)); #print "book_data:"; var_dump($book_data);
			for( $i = 0; $i < count( $book_data['items'] ); $i++ )
			{
				$item = $book_data['items'][$i];
				$data = false;
				$m = false;
				if ( isset( $item["industryIdentifiers"] ) )
				{
					for ( $j = 0; $j < count( $item["industryIdentifiers"] ); $j++ )
					{
						$ind = $item["industryIdentifiers"][$j];
						if ( isset( $ind['type'] ) )
						{
							$isbn = false;
							switch ( $ind['type'] )
							{
								case "ISBN_10":
								case "ISBN_13":
									$isbn = formatISBN($ind['identifier']);#	print "ISBN_format:$isbn";
									break;
							}
							if ( $isbn !== false )
							{
	 							$request = "http://partners.eksmo.ru/wservices/xml/?action=products&isbn=" . urlencode($isbn); #print $request;
#	 							$xml = file_get_contents( $request );
#	 							$mk_data = new SimpleXMLElement($xml); #var_dump($xml);
	 							$mk_data = get_xml( $request ); #var_dump($mk_data);
// 								if ( !is_array($mk_data) )
// 									continue;
								
	 							$i_path = "items.$i.industryIdentifiers.$j.MKData";
	 							if ( isset($mk_data) && isset($mk_data->pages) && isset($mk_data->pages->all ) )
	 							{
	 								if ( $mk_data->pages->all > 0 )
	 								{
	 									foreach( $mk_data->products as $product )
	 									{
											$data['s'] = 20;	#ок
											
											foreach( Yii::app()->params['xls_fields'] as $field => $field_desc )
											{
												$field_val = $product->product->$field;
												$field_type = $field_desc['type'];
												switch ($field_type)
												{
													case 'mkdict' : 
														$mdc_name = 'mk_'.$field;
														$mdc = $mdb_conn->tender->$mdc_name;
														$query = array( $field_desc['id'] => (string)$field_val );
														
														$mk_dict_data = $mdc->findOne($query);
														if (isset($mk_dict_data[$field_desc['value']]))
															$data[$field] = (string)$mk_dict_data[$field_desc['value']];
#														var_dump( $data[$field] );
														break;
													case 'int':
														$data[$field] = (int)$field_val;
														break;
													case 'float':
														$data[$field] = (float)$field_val;
														break;
													case 'string':
														$data[$field] = (string)$field_val;
														break;
														
												}
											} 
											break;
	 									}
	 								}
	 								else
	 								{
	 									
	 									$data['s'] = 23; #не найдена по isbn
	 								}
	 							}
	 							else 
	 							{
	 								$data['s'] = 22; #ошибка разбора xml
	 								#$data['mk_out'] = json_encode($mk_data);
	 							}
	 							
	 							$data['r'] = $request;
	 							$mdb_books->update( 
	 								array("b_id" => (int)$book_id), 
	 								array('$set' => array( $i_path => $data )
	 							) );
	 							$this->_bus->triggerMKCompleteRequest( array_merge( $data, array( 'b' => $arg_data['b'], 'x' => $arg_data['x'] )));
							}
						}
					}
				}  
			}
		} #catch (Exception  $ex) {print $ex->getMessage();}
		#var_dump($data);
		if ( is_array( $data ) )
			$this->_bus->triggerMKCompleteAllRequests( array_merge( $data, array( 'b' => $arg_data['b'], 'x' => $arg_data['x'] )));
		return RET_OK;
	}
	
	private function log($msg)
	{
		echo "mkclient: $msg";
	}

}

?>