<?php
/* @var $this SiteController */

$this->pageTitle=Yii::app()->name;
?>

<?php $this->beginWidget('bootstrap.widgets.TbHeroUnit',array(
    'heading'=>CHtml::encode(Yii::app()->name),
)); ?>

<p>Уточнение стоимости по данным Google.Books и Медиа Каталога.</p>

<?php $this->endWidget(); ?>

<p>Вы можете приступить к работе:</p>

	<div class="row buttons" style="margin-left: 0px; margin-bottom: 20px;">
		<?php $this->widget('bootstrap.widgets.TbButton', array(
			'url' => 'index.php?r=xlsFile/create',
            'type'=>'primary',
            'label'=>'Загрузить новый файл',
        )); ?>
        
		<?php $this->widget('bootstrap.widgets.TbButton', array(
			'url' => 'index.php?r=xlsFile',
            'label'=>'Вывести список загруженых файлов',
        )); ?>
		
	</div>
	
