<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');


class GenExcelCommand extends TenderConsoleCommand
{
    public function actionIndex($type, $limit=5) {  }
    public function actionInit() {  }
    protected $_bus = false;
    
    function __construct($name,$runner)
    {
    	parent::__construct($name,$runner);
    	$this->_bus = new DataBus(Yii::app()->params);
    }
    
    public function run($args)
    {
$this->beginProfile('run');
    	$data = json_decode($args[0], true);					#var_dump($data);
    	$new_xls = Yii::app()->params['xls_files']['done']['path'].'/'.$data['x'];
    	$proc_xls = Yii::app()->params['xls_files']['processing']['path'].'/'.$data['x'];
    	if (!file_exists($proc_xls) ) //fix если запускаем к уже обработанному файлу
    	{
   			$this->complete(file_exists($new_xls)?XLS_STAT_SUCCESS:XLS_STAT_ERR_NO_FILE, $data);
   			exit();
    	}
		$this->log( "start processing $new_xls" );
		$model = XlsFile::model()->getBooksData(new MongoId($data['x']));
		
		require_once dirname(__FILE__).'/../vendors/PHPExcel/PHPExcel.php';
		require_once dirname(__FILE__).'/../vendors/PHPExcel/PHPExcel/Autoloader.php';

		Yii::registerAutoloader(array('PHPExcel_Autoloader','Load'), true);
		try {
			$objPHPExcel = PHPExcel_IOFactory::load($proc_xls);
		} catch( Exception $e)
		{
			$this->log( "ERROR: " .$e->getMessage() . " data: " . json_encode($args) );
			$this->complete(XLS_STAT_ERR_NO_FILE, $data);
			exit();
		}
		
		$styleArray = array(
				'font' => array(
						'underline' => PHPExcel_Style_Font::UNDERLINE_SINGLE
				)
		);
		
		foreach($model as $row)
		{
			foreach ( array_merge( Yii::app()->params['xls_fields'], Yii::app()->params['xls_fields_calc'] ) as $fn => $fp )
			{
				
				if ( !isset($fp['col']) || !isset($row[$fn]) || is_array($row[$fn])) continue;
				$cell = $fp['col'].$row['row_num'];
				if ( trim( $objPHPExcel->getSheet()->getCell($cell)->getValue() ) ) continue; //Не изменяем ячейку, если там было значение 
				$objPHPExcel->getSheet()->setCellValue($cell, $row[$fn]);
				switch ( $fp['type'] )
				{
					case 'int':
						$objPHPExcel->getSheet()->getStyle($cell)->getNumberFormat()->setFormatCode('0');
						break;
				}
				
				if ( $fn == 'name' && $row['isMore'] > 0 )
				{
					$objPHPExcel->getSheet()->getCell($cell)->getHyperlink()->setUrl("http://bib.eksmo.ru/index.php?r=xlsFile/view_books&id={$data['x']}&book_id={$row['id']}");
					$objPHPExcel->getSheet()->getStyle($cell)->applyFromArray($styleArray);
					$objPHPExcel->getSheet()->getStyle($cell)->getFont()->getColor()->setRGB('00A2E8');
				}
			}
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
		$objWriter->save($new_xls);
		
		if ( file_exists($proc_xls) ) 
			unlink($proc_xls);
		
		$this->log( "end processing $new_xls" );
		//Поскольку здесь есть модель файла, сохраним что он обработан

		$this->complete(XLS_STAT_SUCCESS, $data);
$this->endProfile('run');		
		return RET_OK;
    }
    
    private function complete($status = XLS_STAT_SUCCESS, &$data)
    {
    	$xls_model = XlsFile::model()->findByPk(new MongoId($data['x']));
    	$xls_model->end_date = date('Y-m-d H:i:s');
    	$xls_model->status = $status;
    	$xls_model->update();
    	
    	//Сообщим в шину что закончили
    	$this->_bus->triggerXlsComplete( array( 'x' => $data['x'], 's' => $status ) );
    }
    
    public function log($msg)
    {
    	echo "genexcel: $msg";
    }
   
}

?>
