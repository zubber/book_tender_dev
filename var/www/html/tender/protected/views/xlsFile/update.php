<?php
/* @var $this XlsFileController */
/* @var $model XlsFile */

$this->breadcrumbs=array(
	'Xls Files'=>array('index'),
	$model->name=>array('view','id'=>$model->id),
	'Update',
);

$this->menu=array(
	array('label'=>'List XlsFile', 'url'=>array('index')),
	array('label'=>'Create XlsFile', 'url'=>array('create')),
	array('label'=>'View XlsFile', 'url'=>array('view', 'id'=>$model->id)),
	array('label'=>'Manage XlsFile', 'url'=>array('admin')),
);
?>

<h1>Update XlsFile <?php echo $model->id; ?></h1>

<?php $this->renderPartial('_form', array('model'=>$model)); ?>