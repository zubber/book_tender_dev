<?php

/**
 * This is the model class for table "tbl_book".
 *
 * The followings are the available columns in table 'tbl_book':
 * @property string $id
 * @property string $name
 * @property string $author
 * @property integer $cover
 * @property integer $count
 * @property string $xlsFileId
 */
class Book extends EMongoDocument
{
	/**
	 * @return string the associated database table name
	 */
	public function collectionName()
	{
		return 'books';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('name,b_id,xls_id', 'required'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array();
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'b_id' => 'ID',
			'name' => 'Запрашиваемое имя книги',
			'xls_id' => 'XLS ID'
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
	public function search($arSearch = Array(), $project = Array(), $partialMatch = false, $sort = Array())
	{
		$criteria = new EMongoCriteria; //sd(new MongoId($arSearch['xls_id']));
		if (isset($arSearch['xls_id']))
			$criteria->setCondition(array('xls_id' => new MongoId($arSearch['xls_id'])));
		if($this->_id!==null)
			$criteria->compare('_id', new MongoId($this->_id));
		$criteria->setSort( array('_id'=>1));
		$res = new EMongoDataProvider(get_class($this), array(
			'criteria' => $criteria,
			'pagination'=>array(
		        'pageSize'=>50,
		    ),
			
		));
		return $res;
	}
	
	public function getBooksInfo($booksData) {
		//1. получили выборку из books_catalog по $book['search_results']['matches'][0]
		$booksCatalogQuery = array(); 
		$booksCatalogInfo = array();
		$fields = array_merge( Yii::app()->params['xls_fields'], Yii::app()->params['xls_fields_calc'] );
		
		for($i=0; $i<count($booksData); $i++)
		{
			$book = $booksData[$i]['attributes'];
			$book_data = array();		
			if ( isset( $book['search_results'] ) && $book['search_results']['count'] > 0 ) {
				$best_result = $book['search_results']['matches'][0];
				$booksCatalogQuery[] = $best_result['_seq_id'];	
				$booksCatalogInfo[$best_result['_seq_id']] = array(
					'weight' 		=> $best_result['weight'],
					'percentage'	=> $best_result['percentage'],
					'foundCount'	=> $book['search_results']['count'],
				);
			}
		}
		if (count($booksCatalogQuery))
		{
			$booksCatalogQuery = array('_seq_id' => array('$in' => array_unique($booksCatalogQuery)));
			$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
			$booksCatalog = $mdb_conn->tender->books_catalog->find($booksCatalogQuery); //sd($booksCatalog->count()); // sd($booksCatalogQuery);
			
			//2. прошлись по выборке из books_catalog, собрали в массивы все значения словарей (тип mkdict) для разыменований
			$dicts = array();
			$linksToBooks = array();
			foreach( $booksCatalog as $bookInCatalog) {//				s($bookCatalog);
				$book_data = array();
				foreach( $fields as $field_name => $field_params )
				{
					$field_value = "";
					if ( is_array($bookInCatalog) && isset($bookInCatalog[$field_name]) && is_array($bookInCatalog[$field_name]) ) {
					    if (count($bookInCatalog[$field_name] )) // && isset($bookInCatalog[$field_name][0])
						@$propValue = $bookInCatalog[$field_name][0];
					    else
							continue;
					} 
					elseif ( isset($bookInCatalog[$field_name] )) 
						$propValue = $bookInCatalog[$field_name];
					else 
						continue;
					
					switch($field_params['type']) {
						case "mkdict":
							$dictName = $field_name;
							
							if (!isset($dicts[$dictName]))
								$dicts[$dictName] = array('name' => $field_params['id'], 'data' => array(), 'links' => array());
							
							$dicts[$dictName]['data'][] =  $propValue; //значение xml_id
//file_put_contents('/tmp/ff',print_r($bookInCatalog['_seq_id'].':'.$field_name.':'.print_r($bookInCatalog[$field_name],true)."\n",true),FILE_APPEND);
							if (!isset($dicts[$dictName]['links'][$propValue]))
								$dicts[$dictName]['links'][$propValue] = array();
							$dicts[$dictName]['links'][$propValue][] = $bookInCatalog['_seq_id'];
						default:
							$field_value = $propValue;
					}
					
					
					$book_data[$field_name] = $field_value;
				}
				$booksCatalogInfo[$bookInCatalog['_seq_id']] = array_merge($booksCatalogInfo[$bookInCatalog['_seq_id']],$book_data);	
			}

			//3. Прошлись по dicts, собрали значения словарей
			foreach ($dicts as $fieldName => $dictRequestData) {
				$dictQuery = array($dictRequestData['name'] => array('$in' => array_unique($dictRequestData['data'])));
				$dictName = "mk_$fieldName";
				$dictData = $mdb_conn->tender->$dictName->find( $dictQuery );
				foreach($dictData as $dictItem)
					foreach($dictRequestData['links'] as $xlsId => $arSeqId)
						for ($i=0; $i<count($arSeqId); $i++) {
							$booksCatalogInfo[$arSeqId[$i]][$fieldName] = $dictItem['name'];
						}
			} 
		}
		return $booksCatalogInfo;
	}
	
	public function onAfterFind($event) {
		sd($event);
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Book the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
