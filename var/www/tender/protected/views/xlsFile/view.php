<?php
/* @var $this XlsFileController */
/* @var $model XlsFile */

?>

<h1><?php echo CHtml::encode($model->orig_name); ?></h1>

<div id="stat_data">
   <?php $this->renderPartial('_ajaxUpdateStat', array('statData'=>$statData)); ?>
</div>

