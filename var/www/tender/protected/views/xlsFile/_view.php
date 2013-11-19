<?php
/* @var $this XlsFileController */
/* @var $data XlsFile */
?>

<div class="view">

	<b><?php echo CHtml::encode($data->getAttributeLabel('id')); ?>:</b>
	<?php echo CHtml::link(CHtml::encode($data->id), array('view', 'id'=>$data->id)); ?>
	<br />

	<b><?php echo CHtml::encode($data->getAttributeLabel('user_id')); ?>:</b>
	<?php echo CHtml::encode($data->user_id); ?>
	<br />

	<b><?php echo CHtml::encode($data->getAttributeLabel('status')); ?>:</b>
	<?php echo CHtml::encode($data->status); ?>
	<br />

	<b><?php echo CHtml::encode($data->getAttributeLabel('rec_count')); ?>:</b>
	<?php echo CHtml::encode($data->rec_count); ?>
	<br />

	<b><?php echo CHtml::encode($data->getAttributeLabel('orig_name')); ?>:</b>
	<?php echo CHtml::encode($data->orig_name); ?>
	<br />

	<b><?php echo CHtml::encode($data->getAttributeLabel('cr_date')); ?>:</b>
	<?php echo CHtml::encode($data->cr_date); ?>
	<br />


</div>