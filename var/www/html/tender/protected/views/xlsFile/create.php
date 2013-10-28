<?php
/* @var $this XlsFileController */
/* @var $model XlsFile */


$this->menu=array(
	array('label'=>'Вывести список загруженных файлов', 'url'=>array('index')),
);
?>

<h1>Загрузка файла</h1>

<?php /* $this->renderPartial('_form', array('model'=>$model)); */ ?>

<?php if ( $status != false ) { ?>
<div class="alert alert-<?php echo $status['class']; ?>">
	<?php echo $status['text']; ?>
</div>
<?php } ?>

<div class="form">
<?php 
	$form = $this->beginWidget('bootstrap.widgets.TbActiveForm', array(
    	'id'=>'xlsUploadForm',
    	'type'=>'horizontal',
		'htmlOptions' => array( 'enctype'=>'multipart/form-data' ),
	)); 
?>
	<fieldset>
    <?php echo $form->fileFieldRow($model, 'orig_name'); ?>
	</fieldset>
	
	<div class="form-actions">
	    <?php $this->widget('bootstrap.widgets.TbButton', array('buttonType'=>'submit', 'type'=>'primary', 'label'=>'Загрузить')); ?>
	    <?php $this->widget('bootstrap.widgets.TbButton', array('buttonType'=>'reset', 'label'=>'Сбросить')); ?>
	</div>
 
<?php $this->endWidget(); ?>
</div>