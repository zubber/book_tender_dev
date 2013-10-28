<?php

/**
 * This is the model class for table "tbl_xls_file".
 *
 * The followings are the available columns in table 'tbl_xls_file':
 * @property integer $id
 * @property string $user_id
 * @property integer $status
 * @property string $rec_count
 * @property string $orig_name
 * @property string $sys_name
 * @property string $cr_date
 */
class XlsFile extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_xls_file';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('orig_name', 'required'),
			array('status', 'numerical', 'integerOnly'=>true),
			array('user_id', 'length', 'max'=>4),
			array('rec_count', 'length', 'max'=>6),
			array('orig_name', 'file', 'types' => 'xls,xlsx' ),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id, user_id, status, rec_count, orig_name, cr_date', 'safe', 'on'=>'search'),
			array('cr_date','default',
					'value'=>new CDbExpression('NOW()'),
					'setOnEmpty'=>false,'on'=>'insert'
			)
		);
	}

	public function getStat($id)
	{
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_xls = $mdb_conn->tender->stat_xls;
		$xls_data = $mdb_xls->findOne( array( "xls_id" => (int)$id ) );
		
// 		$mdb_gb = $mdb_conn->tender->stat_gb;
// 		$gb_data = $mdb_gb->findOne( array( "date" => date('Y-m-d') ) );
		
		$mdb_qm = $mdb_conn->tender->qm;
		$qm_data = $mdb_qm->findOne( array( "x" => (int)$id ) );
		
// 		db.books.aggregate(
// 		[
// 		{$match:{xls_id:310}},
// 		{ $group : { _id : "$xls_id", n : {$avg:"$search_results.best_percentage"} } }
// 		]
// 		);

/*
		$mdc_books = $mdb_conn->tender->books;
		$match_stat = array(
				array(
						'$match' => array(
								"xls_id" => (int)$id,
						)
				),
				array(
						'$group' => array(
								"_id" => '$xls_id',
								"avg_percentage" => array('$avg' => '$search_results.best_percentage'),
						),
				),
		);
		$results = $mdc_books->aggregate($match_stat);
		$avg = "?";
		if ( isset( $results ) && isset($results['result']) && isset( $results['result'][0] ) && isset( $results['result'][0]['avg_percentage'] ) )
			$avg = round( $results['result'][0]['avg_percentage'], 2 )*100;
*/
		$avg = 0;
		$ready_total = $xls_data['sphinx_stat']['f_0'] + $xls_data['sphinx_stat']['f_1'] + $xls_data['sphinx_stat']['f_2'];
		if ( $ready_total > 0 )
			$avg = round( ( ( $xls_data['sphinx_stat']['f_1'] + $xls_data['sphinx_stat']['f_2'] ) / $ready_total ) * 100 );
		
		$xls_data['sphinx_stat']['average_percentage'] = $avg;
		return( array(
			'xls_data'	=> $xls_data,
			'qm_data'	=> $qm_data,
// 			'gb_data'	=> $gb_data
		));
	}
	
	
	
	/**
	 * метод делает из монговской "плоскую" структуру для грида ( вариант для google books )
	 * @param string $id
	 * @param string $book_id
	 * @return multitype:
	 */
	public function getBooksData($id = false, $book_id = false, $isFirstBook = true )
	{
		if ( !$id )
			$id = $this->id;
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_books = $mdb_conn->tender->books;
		$mdb_books_catalog = $mdb_conn->tender->books_catalog;
	
		$search = array( "xls_id" => (int)$id );
		if ( isset( $book_id ) && $book_id > 0 )
			$search['b_id'] = (int)$book_id;
	
		$m_res = $mdb_books->find( $search );
		$m_cnt = $mdb_books->count( $search );
		$gridData = array();
	
		$fields = array_merge( Yii::app()->params['xls_fields'], Yii::app()->params['xls_fields_calc'] );
		
		foreach( $m_res as $book )
		{
			$book_data = array(
							'id' => $book['b_id'],
							'request' => isset( $book['request'] ) ? $book['request'] : '',
							'isMore' => isset( $book['search_results'] ) ? ( count( $book['search_results']['count'] ) > 1 ? 1 : 0 ) : 0,
							'row_num' => $book['row_num']
			);			

			if ( isset( $book['search_results'] ) && $book['search_results']['count'] > 0 )
			{
				#dd($book['search_results']['matches']);
				$best_result = $book['search_results']['matches'][0];
				$query_book_info = array( "_seq_id" => (int)$best_result["_seq_id"] );
				$book_data['weight'] = $best_result['weight'];
				$book_data['percentage'] = $best_result['percentage'];
				
				$m_book_info = $mdb_books_catalog->findOne( $query_book_info );
				foreach( $fields as $field_name => $field_params )
				{
					$field_value = "";
					if ( isset($m_book_info[$field_name] ) )
					{
						 
						switch($field_params['type']) {
							case "mkdict":
								$mk_dict = "mk_$field_name";
								$query_dict_info = array( $field_params['id'] => $m_book_info[$field_name] ); 
								$m_dict_info = $mdb_conn->tender->$mk_dict->findOne( $query_dict_info );
								$field_value = $m_dict_info[$field_params["value"]];
								#dd($field_value);
								break;
							default:
								$field_value = $m_book_info[$field_name];
						}
					}
					
					$book_data[$field_name] = $field_value;
				}					
			}
			else
			{
				$book_data['weight'] = 0;
				$book_data['percentage'] = 0;
			}
								
			$book_data['original_name'] = $book['name'];
			
			array_push($gridData, $book_data);
		}	
		
		function sort_by_row_num($a, $b)
		{
			if ($a['row_num'] == $b['row_num']) {
				return 0;
			}
			return ($a['row_num'] < $b['row_num']) ? -1 : 1;
		}
		usort($gridData,"sort_by_row_num");
		#dd($gridData);
		return $gridData;
	}
	
	
	
	
	
	/**
	 * метод делает из монговской "плоскую" структуру для грида ( вариант для google books )
	 * @param string $id
	 * @param string $book_id
	 * @return multitype:
	 */
	public function __getBooksData_old($id = false, $book_id = false, $isFirstBook = true )
	{
		if ( !$id )
			$id = $this->id;
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_books = $mdb_conn->tender->books;
		
		$search = array( "xls_id" => (int)$id );
		if ( isset( $book_id ) && $book_id > 0 )
			$search['b_id'] = (int)$book_id;

		$m_res = $mdb_books->find( $search );
		$m_cnt = $mdb_books->count( $search );
		$gridData = array();
		#$m_res->limit(5000)->batchSize(5000);

		foreach( $m_res as $book )
		{
			$book_data = array();
			if ( isset( $book['items'] ) && count( $book['items'] ) > 0 )
			{
				if ( $book_id > 0 )
				{ #dd($book['items']);
					$counter = 1;
					foreach( $book['items'] as $current_book )
					{
						$book_data = $this->_getGBData(
							array_merge( array(
								'id' => $book['b_id'].'_'.$counter,
								'request' => isset( $book['request'] ) ? $book['request'] : '',
								'isMore' => count( $book['items'] ) > 1 ? 1 : 0,
							),
							$current_book),
							$book['name']
						);
						$counter++;
						foreach ( $book_data as $b_data )
							array_push( $gridData, array_merge( $b_data, array('row_num' => $book['row_num'])));
						
					} #dd($gridData);					
					break;
				}
				else
				{
					$first_book = array_shift( $book['items'] );
					$book_data = $this->_getGBData(
						array_merge( array(
							'id' => $book['b_id'],
							'request' => isset( $book['request'] ) ? $book['request'] : '',
							'isMore' => count( $book['items'] ) > 1 ? 1 : 0,
						),
						$first_book),
						$book['name'],
						$isFirstBook
					);
				}
					
			}
			else
			{
				$book_data = array();
				array_push( $book_data, array(
						'id' => $book['b_id'],
						'name' => $book['name'],
						'request' => isset( $book['request'] ) ? $book['request'] : '',
						'pageCount' => "-",
						'canonicalVolumeLink' => '',
						'publishedDate' => "-",
						'selfLink' => "-",
						'ind' => "-",
						'authors' => "-",
						'mk_reminder' => '-',
						'mk_request' => false,
						'mk_price' => "-",
						'isMore' => 0,
				));
			}
			foreach ( $book_data as $b_data )
				array_push( $gridData, array_merge( $b_data, array('row_num' => $book['row_num'])));
		}
		return $gridData;
		
	}
	
	protected function _getGBData($book, $title, $isFirstBook = false)
	{
		$book_data = array(
				'id' => $book['id'],
				'request' => $book['request'],
				'name'	=> $title,
				'isMore' => $book['isMore']
		); #dd($book);
		$book_data['pageCount'] = isset( $book['pageCount'] ) ? $book['pageCount'] : '-' ;
		$book_data['publisher'] = isset( $book['publisher'] ) ? $book['publisher'] : '-';
		$book_data['publishedDate'] =  isset( $book['publishedDate'] ) ? $book['publishedDate'] : '-';
		$book_data['selfLink'] =  isset( $book['selfLink'] ) ? $book['selfLink'] : false;
		$book_data['canonicalVolumeLink'] =  isset( $book['canonicalVolumeLink'] ) ? $book['canonicalVolumeLink'] : false;

		if ( isset( $book["authors"]) )
		{
			$book_data['authors'] = '';
			foreach ( $book["authors"] as $author )
				$book_data['authors'] .= ( $book_data['authors'] ? "," : "" ) .  $author;
		}
		else
			$book_data['authors'] = '-';
		
		$return = array();
		if ( isset( $book["industryIdentifiers"]) )
		{
			foreach ( $book["industryIdentifiers"] as $ind )
			{
				$isbn_data = array_merge( $book_data, array( 'ind' => $ind['type'] . ': ' . $ind["identifier"] ) );
				if ( isset( $ind['MKData'] ) && isset( $ind['MKData']["s"] ) )
				{
					switch($ind['MKData']["s"])
					{
						case 20:
							foreach ( Yii::app()->params['xls_fields'] as $fn => $fp )
							{
								switch($fn)		#Дополнительная обработка
								{
									case 'brgew':
										$isbn_data[$fn] = (int)($ind['MKData'][$fn]*1000);
										break;
									default:
										$isbn_data[$fn] = $ind['MKData'][$fn];
										break;
								}
							}
							foreach ( Yii::app()->params['xls_fields_calc'] as $fn => $fp )
							{
								switch($fn)		#вычисляемые поля
								{
									case 'isbn13':
										$isbn_data[$fn] = $isbn_data['isbnn'];
										break;
									case 'ean13':
										$isbn_data[$fn] = str_replace('-','',$isbn_data['isbnn']);
										break;
									case 'year_pub':
										$isbn_data[$fn] = isset($isbn_data['ldate_d']) ? substr( $isbn_data['ldate_d'], 6, 4 ) : '';
										break;
								}
							}
							break;
						case 22:
							$isbn_data['price'] = "ошибка xml";
							break;
						case 23:
						default:
							$isbn_data['price'] = "";
							break;
									
					}
					$isbn_data['mk_request'] = $ind['MKData']['r'];
				}
				else # не-isbn типы
				{
					$isbn_data['mk_reminder'] = '';
					$isbn_data['mk_price'] = "";
					$isbn_data['mk_request'] = '';
				}
				
				array_push($return,$isbn_data);
			}
			if ( $isFirstBook ) #нужно оставить только 1 результат ( релевантный ) 
				array_shift($return);
		}
		
		if ( !isset( $book["industryIdentifiers"]) || count( $return ) == 0 )
		{
			array_push($return, 
				array_merge( 
					array( 
						'ind' => '-',
						'mk_reminder' => '-',
						'mk_price' => '-',
						'mk_request' => '-',
						'ind' => '-'
					),
				$book_data)
			);
		}
		return $return;
	}
	
	/**
	 * Возвращает ссылку на готовый файл, если таковой существует. 
	 * Используется для отображения ссылки "Скачать"
	 * 
	 * @param int $xls_id идентификатор файла 
	 */
	
	public function getCompletedFile($xls_id)
	{
		$fullname = Yii::app()->params['xls_files']['done']['path'].'/'.$xls_id;
		return file_exists( $fullname ) ? $fullname : false;
	}
	
	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'user_id' => 'User',
			'status' => 'Status',
			'rec_count' => 'Rec Count',
			'orig_name' => 'Файл Excel',
			'cr_date' => 'Cr Date',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('user_id',$this->user_id,true);
		$criteria->compare('status',$this->status);
		$criteria->compare('rec_count',$this->rec_count,true);
		$criteria->compare('orig_name',$this->orig_name,true);
		$criteria->compare('cr_date',$this->cr_date,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return XlsFile the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
