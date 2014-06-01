<?php

class XlsFile extends EMongoDocument
{
	public $_id;
	public $orig_name;
	public $user_id;
	
	public function collectionName()
	{
		return 'xls_file';
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
			array('user_id', 'EMongoIdValidator'),
			array('rec_count', 'length', 'max'=>6),
			array('orig_name', 'file', 'types' => 'xls,xlsx' ),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id,_id, user_id, status, rec_count, orig_name, cr_date', 'safe', 'on'=>'search'),
// 			array('cr_date','default',
// 					'value'=>new CDbExpression('NOW()'),
// 					'setOnEmpty'=>false,'on'=>'insert'
// 			)
		);
	}
	
    public function beforeSave()
    {
        if (parent::beforeSave())
        {
            if ($this->isNewRecord)
                $this->cr_date = new MongoDate();
            return true;
        } 
        else
            return false;
    }

	public function getStat($id)
	{
		$id = new MongoId($id);
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_xls = $mdb_conn->tender->stat_xls;
		$xls_data = $mdb_xls->findOne( array( "xls_id" => $id ) );
		$mdb_qm = $mdb_conn->tender->qm;
		$qm_data = $mdb_qm->findOne( array( "x" => $id ) );
		$avg = 0;
		$ready_total = $xls_data['sphinx_stat']['f_0'] + $xls_data['sphinx_stat']['f_1'] + $xls_data['sphinx_stat']['f_2'];
		if ( $ready_total > 0 )
			$avg = round( ( ( $xls_data['sphinx_stat']['f_1'] + $xls_data['sphinx_stat']['f_2'] ) / $ready_total ) * 100 );
		$xls_data['sphinx_stat']['average_percentage'] = $avg;
		$ret = array(
			'xls_data'	=> $xls_data,
			'qm_data'	=> $qm_data,
		);
		return($ret);
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
	
		$search = array( "xls_id" => $id );
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
			$book_data['original_author'] = isset( $book['author']) ? $book['author'] : '';
			
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
			'_id' => 'ID',
			'user_id' => 'User',
			'status' => 'Status',
			'rec_count' => 'Rec Count',
			'orig_name' => 'Файл Excel',
			'cr_date' => 'Cr Date',
		);
	}

	public function search($criteria)
	{ sd($criteria);
		if (!isset($criteria)) {
			$criteria = new EMongoCriteria;
	
			if($this->_id!==null)
				$criteria->compare('_id', new MongoId($this->_id));
		}
		return new EMongoDataProvider(get_class($this), array(
				'criteria' => $criteria,
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
