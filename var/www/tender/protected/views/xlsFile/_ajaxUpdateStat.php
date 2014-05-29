<?php
	$xlsData = $statData['xls_data'];
	$qmData = $statData['qm_data'];
	if ( isset($xlsData['cdate']) && (int)$xlsData['cdate'] > 0) $xlsData['cdate'] = date('Y-m-d H:i:s',$xlsData['cdate']);
	if ( isset($xlsData['edate']) && (int)$xlsData['edate'] > 0 ) $xlsData['edate'] = date('Y-m-d H:i:s',$xlsData['edate']);
?>
<div style="float:left; width:50%;">
<h5>Статистика обработки файла</h5>
</div>
<div style="float:left; width:50%; text-align: right;">
<?php
if ( isset( $qmData['is_complete'] ) && isset( $xlsData['xls_id']) && $qmData['is_complete'] > 1 && $xlsData['xls_id'] > 0 )
{
	$this->widget('bootstrap.widgets.TbButton', array(
			'label'=>'Скачать обработанный файл',
			'type'=>'success',
			'url'=>'index.php?r=xlsFile/downloadFile&id='. $xlsData['xls_id'],
	));
}
?>
</div>
<div style="clear: both;"></div>
<?php 
	
	$xlsDataProvider = new CArrayDataProvider(array(array(
			'id'=>1,
			'cdate'=>isset( $xlsData['cdate'] ) ? $xlsData['cdate'] : "-",
			'edate'=>isset( $xlsData['edate'] ) ? $xlsData['edate'] : "-",
			'rows_tasked'=>isset($xlsData['rows_tasked'] ) ? $xlsData['rows_tasked'] : "-",
			'rows_empty'=>isset( $qmData['rec_empty'] ) ? $qmData['rec_empty'] : "-",
			'rows_total'=>isset( $xlsData['rows_total'] ) ? $xlsData['rows_total'] : "-",
	)));
	
	$this->widget('bootstrap.widgets.TbGridView', array(
			'type'=>'striped bordered condensed',
			'dataProvider' => $xlsDataProvider,
			'columns'=>array(
					array('name'=>'cdate', 'header'=>'Начало обработки'),
					array('name'=>'edate', 'header'=>'Окончание обработки'),
					array('name'=>'rows_total', 'header'=>'Строк в excel'),
					array('name'=>'rows_empty', 'header'=>'Пустых строк'),
					array('name'=>'rows_tasked', 'header'=>'Добавлено в очередь'),

			),
			'template'=>"{items}",
	));
	
	
?>
<h5>Статистика поиска Sphinx</h5>
<?php 
	if ( isset($xlsData['sphinx_stat']) )
	{
		$sphinxDataProvider = new CArrayDataProvider(array(array(
			'id'=>1,
			'sphinx_f0'=>isset($xlsData['sphinx_stat']['f_0']) ? $xlsData['sphinx_stat']['f_0'] : "-",
			'sphinx_f1'=>isset($xlsData['sphinx_stat']['f_1'])?$xlsData['sphinx_stat']['f_1']:"-",
			'sphinx_f2'=>isset($xlsData['sphinx_stat']['f_2'])?$xlsData['sphinx_stat']['f_2']:"-",
			'average_percentage'=>isset($xlsData['sphinx_stat']['average_percentage'])?$xlsData['sphinx_stat']['average_percentage']:"-",
		)));
	
		$this->widget('bootstrap.widgets.TbGridView', array(
				'type'=>'striped bordered condensed',
				'dataProvider' => $sphinxDataProvider,
				'columns'=>array(
					array('name'=>'sphinx_f0', 'header'=>'Не найдено'),
					array('name'=>'sphinx_f1', 'header'=>'Точных совпадений'),
					array('name'=>'sphinx_f2', 'header'=>'Неточных совпадений'),
					array('name'=>'average_percentage', 'header'=>'Процент совпадений'),
				),
				'template'=>"{items}",
		));
	}
// 	array('name'=>'average_percentage', 'header'=>'Средний процент совпадений'),
	
?>


<?php 
/*
 * <h5>Статистика Медиа Каталога</h5>
	$mkDataProvider = new CArrayDataProvider(array(array(
		'id'=>1,
		'mk_c'=>$xlsData['mk_stat']['c'],
		'mk_c20'=>$xlsData['mk_stat']['c_20'],
		'mk_c21'=>$xlsData['mk_stat']['c_21'],
		'mk_c22'=>$xlsData['mk_stat']['c_22'],
		'mk_c23'=>$xlsData['mk_stat']['c_23'],
		'mk_c24'=>$xlsData['mk_stat']['c_24'],
	)));
	
	$this->widget('bootstrap.widgets.TbGridView', array(
			'type'=>'striped bordered condensed',
			'dataProvider' => $mkDataProvider,
			'columns'=>array(
				array('name'=>'mk_c', 'header'=>'Запросов к Медиа Каталогу'),
				array('name'=>'mk_c22', 'header'=>'	ошибок разбора xml:'),
				array('name'=>'mk_c23', 'header'=>'	не найдена по isbn:'),
				array('name'=>'mk_c24', 'header'=>'	только с 0 остатком:'),
				array('name'=>'mk_c20', 'header'=>'	найдено:'),
			),
			'template'=>"{items}",
	));
*/	
?>