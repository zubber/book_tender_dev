<?php
/* @var $this XlsFileController */
/* @var $dataProvider CActiveDataProvider */

$this->breadcrumbs=array(
	'Xls Files',
);

$this->menu=array(
	array('label'=>'Добавить файл', 'url'=>array('create')),
);
?>

<h1>Ваши файлы:</h1>

<?php 
// sd($dataProvider);
	$gridDataProvider = $dataProvider;
	$sort = new CSort('XlsFile');
	$sort->defaultOrder = 'cr_date DESC';
	$gridDataProvider->setSort($sort);
	$gridDataProvider->pagination = false;
	$this->widget('bootstrap.widgets.TbGridView', array(
	    'type'=>'striped bordered condensed',
	    'dataProvider'=>$gridDataProvider,
	    'template'=>"{items}",
	    'columns'=>array(
	        array(
	        		'name'=>'orig_name', 
	        		'header'=>'Название',
	        		'type'=>'raw',
	        		'value' => '"<a href=\'/index.php?r=xlsFile/view&id=". $data->_id . "\'>$data->orig_name</a>"'
			),
    		array(
    				'name'  => 'status',
    				'type'  => 'raw',
					'value'=> array($this,'indexGridStatus'), 
					'header'=>'Статус'
	   		),
	    	array('name'=>'cr_date', 'header'=>'Загружен', 'type'=>'raw', 'value' => '$data->cr_date instanceof MongoDate ? date("Y-m-d H:i:s",$data->cr_date->sec) : $data->cr_date'),
			array('name'=>'end_date', 'header'=>'Обработан', 'type'=>'raw'),
//	    	array('name'=>'download', 'header'=>'Скачать', 'type'=>'raw'),
	    ),
	));
?>
