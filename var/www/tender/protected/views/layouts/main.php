<?php /* @var $this Controller */ ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="language" content="en" />

    <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->theme->baseUrl; ?>/css/styles.css" />
	<script type="text/javascript" src="/assets/js/tender.js"></script>
    
	<title><?php echo CHtml::encode($this->pageTitle); ?></title>

	<?php Yii::app()->bootstrap->register(); ?>
	
	<script>
		<?php echo Yii::app()->controller->globalVars; ?>
	
		jQuery(document).ready( function() {
			<?php echo Yii::app()->controller->onloadScript; ?>
		});
	</script>
</head>

<body>

<?php $this->widget('bootstrap.widgets.TbNavbar',array(
    'items'=>array(
        array(
            'class'=>'bootstrap.widgets.TbMenu',
            'items'=>array(
                array('label'=>'Добавить файл', 'url'=>array('/xlsFile/create')),
				array('label'=>'Загруженные файлы', 'url'=>array('/xlsFile/')),
				'---',
				array('label'=>Yii::app()->user->name, 'url'=>'#', 'visible'=>!Yii::app()->user->isGuest, 'items'=>array(
					array('label'=>'Профиль', 'url'=>array('/user/update&id='.Yii::app()->user->id)),
					'---',
					array('label'=>'Выйти', 'url'=>array('/site/logout'))	
				)),
				array('label'=>'Войти', 'url'=>array('/site/login'), 'visible'=>Yii::app()->user->isGuest),

            ),
        ),
    ),
)); ?>

<div class="container" id="page">
<?php 

/* 	<?php if(isset($this->breadcrumbs)):?>
		<?php $this->widget('bootstrap.widgets.TbBreadcrumbs', array(
			'links'=>$this->breadcrumbs,
		)); ?><!-- breadcrumbs -->
	<?php endif?>
*/
?>
	<?php echo $content; ?>

	<div class="clear"></div>

	<div id="footer">
		Поддержка <a href="mailto:zubran@gmai.com">zubran@gmai.com</a><br/>
		<?php echo Yii::powered(); ?>
	</div><!-- footer -->

</div><!-- page -->

</body>
</html>
