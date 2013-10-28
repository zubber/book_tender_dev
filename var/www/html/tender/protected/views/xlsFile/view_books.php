<?php
/* @var $this XlsFileController */
/* @var $model XlsFile */
#require '/var/www/Kint/Kint.class.php';
$this->menu = array();
$book_id = isset($_GET['book_id']) && $_GET['book_id'] > 0 ? $_GET['book_id'] : 0;
if ( isset( $_GET['book_id'] ))
	array_push( $this->menu, array('label'=>'Данные Google Books по этому файлу', 'url'=>array('view_gb&id='. $_GET['id']),) );

array_push( $this->menu,
	array('label'=>'Статистика этого файла', 'url'=>array('view&id='.$_GET['id'])),
	array('label'=>'Перейти к списку загруженных файлов', 'url'=>array('index'))
);

?>

<h1><?php echo CHtml::encode($model->orig_name); ?></h1>

<div style="float:left; width:50%;">
<h5>Детализированный отчет по найденным совпадениям</h5>
</div>
<div style="float:left; width:50%; text-align: right;">
<?php 
if ( $model['status'] == 1 )
{
	$this->widget('bootstrap.widgets.TbButton', array(
			'label'=>'Скачать обработанный файл',
			'type'=>'success',
			'url'=>'index.php?r=xlsFile/downloadFile&id='. $model['id'],
	));
}
?>
</div>
<div style="clear: both;"></div>


<?php 



// dd($gridData);
	$gridDataProvider = new CArrayDataProvider($gridData);
	$gridDataProvider->pagination = false;
	$gridParams = array(
	    'dataProvider'=>$gridDataProvider,
#		'mergeColumns' => array('id', 'name', 'price_authors','isMore'),
		'itemsCssClass'=>'table table-bordered table-condensed',
		'summaryText'=>'',
	    'columns'=>array(
	        array('name'=>'id', 'header'=>'№'),
	    	array('name'=>'original_name', 'header'=>'Входное назв.'),
	    	array('name'=>'name', 'header'=>'Найденное назв.'),
// 	    	array(
// 	    			'name'=>'name',
// 	    			'header'=>'Название',
// 	    			'value' => 	'$data["canonicalVolumeLink"] ? "<a id=\'gr_name_".$data["id"]."\' href=\'" . $data["canonicalVolumeLink"] . "\' target=_blank>" . $data["name"]. "</a>" : "<span id=\'gr_name_".$data["id"]."\'>".$data["name"]."</span>"',
// 	    			'type'=>'raw'),
	    	array('name'=>'price_authors', 'header'=>'Авторы'),
// 	        array(
// 	        		'name'=>'authors',
// 	        		'header'=>'Авторы',
// 	        		'type'=>'raw',
// 	        		'value' => 	'"<span id=\'gr_name_".$data["id"]."\'>".$data["authr"]."</span>"',
// 	    	),
	        array('name'=>'publi', 'header'=>'Издательство'),
	    	array('name'=>'sdate_d', 'header'=>'Дата изд.'),
	    	array('name'=>'isbnn', 'header'=>'ISBN', 'type'=>'raw'),
	    	array('name'=>'price', 'header'=>'Цена', 'type'=>'raw'),
	    	array('name'=>'remainder', 'header'=>'Остаток', 'type'=>'raw'),
	    	array('name'=>'percentage', 'header'=>'Совпадение,%', 'type'=>'raw',
	    			'value' => '$data["percentage"]*100',
	    	),
//     		array(
//     			'name'=>'mk_request',
//     			'header'=>'MK:request',
//     			'value' => '$data["mk_request"] ? "<a href=\'" . $data["mk_request"] . "\' target=_blank>#</a>" : "-"',
//     			'type'=>'raw'),

// 	    	array(
// 				'name'=>'gb_request',
// 				'header'=>'GB:request',
// 				'value' => '$data["request"] ? "<a href=\'" . $data["request"] . "\' target=_blank>#</a>" : "-"',
// 				'type'=>'raw'),
	    		
// 	    	array(
// 				'name'=>'selfLink',
// 				'header'=>'GB:selfLink',
// 				'value' => '$data["selfLink"] ? "<a id=\'gr_name_".$data["id"]."\' href=\'" . $data["selfLink"] . "\' target=_blank>#</a>" : "-"',
// 				'type'=>'raw'),
	    		
	    ),
	);
// 	if ( ! ( $book_id > 0 ) )
// 		array_push($gridParams['columns'], 
// 			array(
// 				'name'=>'isMore', 
// 				'header'=>'Еще',
// 				'value' => '"<a id=\'gr_ismore_".$data["id"]."\' href=\'/tender/index.php?r=xlsFile/view_books&id=". $_GET["id"] . "&book_id=".$data["id"]."\'>".($data["isMore"] > 0 ? "#" : "" ) ."</a>"',
// 	    		'type' => 'raw'
// 		) );
	
	$this->widget('ext.groupgridview.GroupGridView', $gridParams );
 ?>

