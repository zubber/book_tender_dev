<?php

/**
 * This is the model class for table "employee".
 *
 * The followings are the available columns in table 'employee':
 * @property integer $emp_id
 * @property string $emp_full_name
 * @property integer $emp_age
 * @property string $emp_phone
 * @property string $emp_address
 * @property integer $emp_wages
 */
class Employee extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'employee';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('emp_age, emp_wages', 'numerical', 'integerOnly'=>true),
			array('emp_full_name', 'length', 'max'=>80),
			array('emp_phone', 'length', 'max'=>12),
			array('emp_address', 'length', 'max'=>100),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('emp_id, emp_full_name, emp_age, emp_phone, emp_address, emp_wages', 'safe', 'on'=>'search'),
		);
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
			'emp_id' => 'Emp',
			'emp_full_name' => 'Emp Full Name',
			'emp_age' => 'Emp Age',
			'emp_phone' => 'Emp Phone',
			'emp_address' => 'Emp Address',
			'emp_wages' => 'Emp Wages',
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

		$criteria->compare('emp_id',$this->emp_id);
		$criteria->compare('emp_full_name',$this->emp_full_name,true);
		$criteria->compare('emp_age',$this->emp_age);
		$criteria->compare('emp_phone',$this->emp_phone,true);
		$criteria->compare('emp_address',$this->emp_address,true);
		$criteria->compare('emp_wages',$this->emp_wages);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Employee the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
