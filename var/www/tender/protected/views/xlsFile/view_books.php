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
// dd($booksCatalogData);
class catalogRecord {
	static protected $booksCatalogData;
	public function __construct(&$booksCatalogData) {
		self::$booksCatalogData = $booksCatalogData;
	} 
	
	static public function renderCatalogField($name,&$data) { //dd($data);
		if (
			isset( $data->search_results ) 
			&& isset( $data->search_results["matches"] ) 
			&& isset($data->search_results["matches"][0])
		) {
// 			dd($name,$data->search_results,self::$booksCatalogData);
			$seq_id = $data->search_results["matches"][0]['_seq_id'];
			switch($name) {
				case 'name':
					$ret = '<a href="javascript:/*seq_id='.$seq_id.'&book_id='.$data->b_id.'*/;">'.self::$booksCatalogData[$seq_id][$name].'</a>';
					break;
				default:
					$ret = isset(self::$booksCatalogData[$seq_id][$name]) ? self::$booksCatalogData[$seq_id][$name] : '';
			}
			return $ret;
		}
		else 
			return "";
	}
}
new catalogRecord($booksCatalogData);
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
// dd($booksData);
// dd($gridData);
// 	$gridDataProvider = new CArrayDataProvider($gridData);
// 	$gridDataProvider->pagination = true;
// dd(	$booksInfo );
	$gridParams = array(
	    'dataProvider'=>$booksData,
#		'mergeColumns' => array('id', 'name', 'price_authors','isMore'),
		'itemsCssClass'=>'table table-bordered table-condensed',
		'summaryText'=>'',
	    'columns'=>array(
	        array('name'=>'row_num', 'header'=>'№'),
	    	array('name'=>'name', 'header'=>'Вход. назв.'),
	    	array('name'=>'author', 'header'=>'Вход. автор'),
// 	    	array('name'=>'found_name', 'header'=>'Найденное назв.'),
	    	array(
    			'name'=>'found_name',
    			'header'=>'Найденное назв.',
	    		'value' => 'catalogRecord::renderCatalogField("name",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'price_authors',
    			'header'=>'Найденный автор',
	    		'value' => 'catalogRecord::renderCatalogField("price_authors",$data)',
	   			'type'=>'raw'),
	    		
	    	array(
    			'name'=>'publi',
    			'header'=>'Издательство',
	    		'value' => 'catalogRecord::renderCatalogField("publi",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'sdate_d',
    			'header'=>'Дата изд.',
	    		'value' => 'catalogRecord::renderCatalogField("sdate_d",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'isbnn',
    			'header'=>'ISBN',
	    		'value' => 'catalogRecord::renderCatalogField("isbnn",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'price',
    			'header'=>'Цена',
	    		'value' => 'catalogRecord::renderCatalogField("price",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'remainder',
    			'header'=>'Остаток',
	    		'value' => 'catalogRecord::renderCatalogField("remainder",$data)',
	   			'type'=>'raw'),
	    	array(
    			'name'=>'percentage',
    			'header'=>'Совпадение,%',
	    		'value' => '(int)catalogRecord::renderCatalogField("percentage",$data)*100',
	   			'type'=>'raw'),
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

