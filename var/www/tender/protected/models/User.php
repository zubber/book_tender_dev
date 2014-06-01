<?php

class User extends EMongoDocument
{
	public $_id;
	
	public function collectionName()
	{
		return 'users';
	}
	
	function relations(){
		return array(
			'xls_file' => array('many','XlsFile','user_id'),
		);
	}
	
	public function behaviors(){
	  return array(
  		'EMongoTimestampBehaviour' => array(
  			'class' => 'EMongoTimestampBehaviour' // Adds a handy create_time and update_time
  	  ));
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('username, password, email', 'required'),
			array('username, password, email', 'length', 'max'=>128),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('_id, username, password, email', 'safe', 'on'=>'search'),
			array('password', 'safe', 'on'=>'update'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'_id' => 'ID',
			'username' => 'Логин',
			'password' => 'Пароль',
			'email' => 'Email',
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
		$criteria = new EMongoCriteria;

		if($this->_id!==null)
			$criteria->compare('_id', new MongoId($this->_id));
		//$criteria->compare('__v', $this->__v);
		return new EMongoDataProvider(get_class($this), array(
			'criteria' => $criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	/**
	 * Hashes our password, taken straight from the tutorial
	 * @return string
	 */
	function hashPassword(){
		return crypt($this->password,$this->blowfishSalt());
	}

	function beforeSave(){
		//$this->password=$this->hashPassword(); // lets hash that shiz
		return parent::beforeSave();
	}

	/**
	 * Generate a random salt in the crypt(3) standard Blowfish format.
	 *
	 * @param int $cost Cost parameter from 4 to 31.
	 *
	 * @throws Exception on invalid cost parameter.
	 * @return string A Blowfish hash salt for use in PHP's crypt()
	 */
	function blowfishSalt($cost = 13)
	{
		if (!is_numeric($cost) || $cost < 4 || $cost > 31) {
			throw new Exception("cost parameter must be between 4 and 31");
		}
		$rand = array();
		for ($i = 0; $i < 8; $i += 1) {
			$rand[] = pack('S', mt_rand(0, 0xffff));
		}
		$rand[] = substr(microtime(), 2, 6);
		$rand = sha1(implode('', $rand), true);
		$salt = '$2a$' . sprintf('%02d', $cost) . '$';
		$salt .= strtr(substr(base64_encode($rand), 0, 22), array('+' => '.'));
		return $salt;
	}
	
	function findById($id) {
	    $id = (int)$id;
	    return $this->findOne(array('id' => $id));
	}
}
