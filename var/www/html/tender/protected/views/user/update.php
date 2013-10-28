<?php
/* @var $this UserController */
/* @var $model User */


// $this->menu=array(
// 	array('label'=>'List User', 'url'=>array('index')),
// 	array('label'=>'Create User', 'url'=>array('create')),
// 	array('label'=>'View User', 'url'=>array('view', 'id'=>$model->id)),
// 	array('label'=>'Manage User', 'url'=>array('admin')),
// );
?>

<h1>Профиль <?php echo $model->username; ?></h1>

<?php

 	if ( isset( $status ) )
 	{
		Yii::app()->user->setFlash($status['class'], $status['text'] );
		$this->widget('bootstrap.widgets.TbAlert', array(
				'block'=>true, // display a larger alert block?
				'fade'=>true, // use transitions?
				'closeText'=>'&times;', // close link text - if set to false, no close link is displayed
				'alerts'=>array( // configurations per alert type
						'success'=>array('block'=>true, 'fade'=>true, 'closeText'=>'&times;'), // success, info, warning, error or danger
				),
		    ));
 	}
	$this->renderPartial('_form', array('model'=>$model)); 
?>