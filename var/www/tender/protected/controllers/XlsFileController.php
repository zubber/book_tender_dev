<?php
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

class XlsFileController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column2';
	public $onloadScript = '';
	public $globalVars = '';
	
	
	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index','view','view_books', 'downloadFile'),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('create','ajaxUpdateStat'),
				'users'=>array('@'),
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array('admin','delete'),
				'users'=>array('admin'),
			),
// 			array('deny',  // deny all users
// 				'users'=>array('*'),
// 			),
		);
	}

	public function checkAuthorized($user_id = false, $params=array())
	{
		if( ( $user_id === false && Yii::app()->user->isGuest ) || ( $user_id > 0 && Yii::app()->user->id != $user_id) )
		{
			$this->redirect(array('site/login'));
		}
	}	
	
	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
Yii::beginProfile('actionIndex');
		$this->checkAuthorized();
Yii::beginProfile('model');
		$dataProvider=new CActiveDataProvider('XlsFile',array(
				'criteria'=>array(
						'condition'=>'user_id='.Yii::app()->user->id,
						'order'=>'cr_date DESC',
				)
		));
Yii::endProfile('model');
		$this->render('index',array(
				'dataProvider'=>$dataProvider,
		));
Yii::endProfile('actionIndex');
	}
	
	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		$model = $this->loadModel($id);
		$this->checkAuthorized($model->user_id);
		$statData = $model->getStat($id);
// dd($statData);
		
		$this->globalVars = "var xlsFile = $id;";
		$this->onloadScript = "updateStatTimer();";
		$this->menu = $this->drawMenu('view', $model);
		$this->render('view',array(
			'model'		=> $model,
			'statData'	=> $statData
		));
	}
	
	public function actionView_Books($id, $book_id = false)
	{
		$model = $this->loadModel($id);
		$this->checkAuthorized($model->user_id);
		$booksData = Book::model()->search(array('xls_id' => $id));
		$booksCatalogData = Book::model()->getBooksInfo($booksData->getData());
		$this->render('view_books',array(
			'model' => $model,
			'booksData' => $booksData, 
			'booksCatalogData'	=> $booksCatalogData
		));
	}

	public function actionDownloadFile($id)
	{
		$this->checkAuthorized();
		$file_name = $id.'.xlsx';
		$full_name = Yii::app()->params['xls_files']['done']['path'] . "/$id";
		header('Content-Transfer-Encoding: binary');
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Length: ' . filesize($full_name));
		header('Content-Disposition: attachment; filename='.$file_name);
		readfile($full_name);
		Yii::app()->end();
		return;
	}
	
	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 */
	public function actionCreate()
	{
		$this->checkAuthorized();
		$model=new XlsFile;

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		$status = false;
		if(isset($_POST['XlsFile']))
		{
			$model->attributes=$_POST['XlsFile'];
			$model->user_id = Yii::app()->user->id;
			$model->orig_name=CUploadedFile::getInstance($model,'orig_name');
			$sys_file = CUploadedFile::getInstance($model,'orig_name');
			if($model->save())
			{
				$sys_name = Yii::app()->params['xls_files']['uploaded']['path']."/".$model->id;
				$sys_file->saveAs($sys_name);
				$bus = new DataBus(Yii::app()->params);
				$msg_data = array( 'f' => $sys_name, 'i' => $model->id );
				if ( $bus->triggerXlsUpload( $msg_data ) )
				{
					$this->redirect(array('view','id'=>$model->id));
				}
				else
				{
					$status = array( 'text' => 'Ошибка коммуникации с шиной данных. Пожалуйста, свяжитесь с технической поддержкой.', 'class' => 'error' );
				}
			}
			else
				$status = array( 'text' => 'Ошибка сохранения "'. $model->orig_name . '"', 'class' => 'error' );
		}

		$this->render('create',array(
			'model'		=> $model,
			'status'	=> $status,
		));
	}

	public function actionAjaxUpdateStat()
	{
		$id = (int)Yii::app()->request->getQuery('xls_file'); //dd(XlsFile::model());
		if( !($id > 0)) exit( print( json_encode( array('errorText' => 'xls_file is not number' ))));
		$owner_id = XlsFile::model()->findByPk($id)->user_id;
		$this->checkAuthorized($owner_id,array('js'=>true));
		$statData = XlsFile::model()->getStat($id);
// dd($statData);		
		$this->renderPartial('_ajaxUpdateStat', array( 'statData' => $statData ), false, true);
	}
	
	
	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return XlsFile the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
 		$model=XlsFile::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}
	
	protected function indexGridStatus($data,$row)
	{
		switch($data['status'])
		{
			case XLS_STAT_IN_QUEUE:			$status = array( 'type'=> 'default', 'label' => 'в очереди' ); break;
			case XLS_STAT_BEGIN_PROCESSING:	$status = array( 'type'=> 'info', 'label' => 'в обработке' ); break;
			case XLS_STAT_CREATE_XLS:		$status = array( 'type'=> 'info', 'label' => 'создание xls' ); break;
			case XLS_STAT_SUCCESS:			$status = array( 'type'=> 'success', 'label' => 'обработан' ); break;
			case XLS_STAT_ERR_NO_FILE:		$status = array( 'type'=> 'important', 'label' => 'нет файла' ); break;			
		}
		$this->widget('bootstrap.widgets.TbLabel', $status);
	}

// 	protected function indexGridOrigName($data,$row)
// 	{
// 		"<a href='/tender/index.php?r=xlsFile/view&id=";
// 	}
	
	protected function indexGridOrigName($data,$row)
	{
		$this->widget('bootstrap.widgets.TbLabel', array(
				'type'=> ( $data['status'] > 0 ? 'success' : 'default' ),
				'label'=> ( $data['status'] > 0 ? 'обработан' : 'в обработке' )
		));
	}
	
	public function drawMenu($view_name, $model)
	{
		$comp_file = XlsFile::model()->getCompletedFile($model->id);
		switch ($view_name)
		{
			case 'view':
				$menu = array( array('label'=>'Данные книг из этого файла', 'url'=>array('view_books&id='. $model->id),) );
				if ( $comp_file )
					array_push( $menu, array('label'=>'Скачать обработанный файл', 'url'=>array('downloadFile&id='. $model->id),) );
				array_push( $menu, array('label'=>'Вывести список загруженных файлов', 'url'=>array('index') ) );
				break;
		}
		return $menu;
		
	}
	
	
	
	
	
	
	
	
	
	
	/**
	 * UNUSED STAFF
	 */
	
	
	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate($id)
	{
		$this->checkAuthorized();
		$model=$this->loadModel($id);

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		if(isset($_POST['XlsFile']))
		{
			$model->attributes=$_POST['XlsFile'];
			if($model->save())
				$this->redirect(array('view','id'=>$model->id));
		}

		$this->render('update',array(
			'model'=>$model,
		));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$this->checkAuthorized();
		$this->loadModel($id)->delete();

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax']))
			$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
	}
	


	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$this->checkAuthorized();
		$model=new XlsFile('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['XlsFile']))
			$model->attributes=$_GET['XlsFile'];

		$this->render('admin',array(
			'model'=>$model,
		));
	}


	/**
	 * Performs the AJAX validation.
	 * @param XlsFile $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='xls-file-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
