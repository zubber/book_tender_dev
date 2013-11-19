<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

define('RET_ERR_NO_FILE', 1 );

class ProcessExcelCommand extends CConsoleCommand
{
    public function actionIndex($type, $limit=5) {  }
    public function actionInit() {  }
    protected $_bus = false;
    
    function __construct($name,$runner)
    {
    	parent::__construct($name,$runner);
    	$this->_bus = new DataBus(Yii::app()->params);
    }
    
    protected function _importExcel($inputFileName, $xlsFileId)
    {
    	require_once dirname(__FILE__).'/../vendors/PHPExcel/PHPExcel.php';
    	require_once dirname(__FILE__).'/../vendors/PHPExcel/PHPExcel/Autoloader.php';
    	Yii::registerAutoloader(array('PHPExcel_Autoloader','Load'), true);
    	
    	$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
    	$sheet_array = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
    	 
    	$counter = 0;
    	$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
    	$mdb_books = $mdb_conn->tender->books;
    	$mdb_seq = $mdb_conn->tender->sequences;
    	$seq_query = array( '_id' => 'b_id' );
    	$seq_mod = array( '$inc' => array('seq' => 1) );
    	$seq_ret = array( 'seq' => 1 );
    	$is_success_save = 1;
    	$this->_bus->triggerXlsGetRecordCount( array( 'x' => $xlsFileId, 'c' => count($sheet_array) - 1));
    	
	    foreach ( $sheet_array as $row ) {
	    	$counter++;
	    	if ( $counter == 1 ) continue;
	    	$name		= $row['C'];

	    	if ( !trim($name) )
	    	{
	    		$this->_bus->triggerXlsRecordEmpty(array('x' => $xlsFileId));
	    		continue;
	    	}
	    	$book_name	= $row['C'];
	    	$author		= $row['E'];
	    	$cover		= $row['F'];
	    	$count		= (int)$row['Y'];
			$seq = $mdb_seq->findAndModify($seq_query,$seq_mod,$seq_ret);
			
    		$m_row = array(
    			'b_id'		=> (int)$seq['seq'],
    			'name'		=> $book_name,
    			'author'	=> $author,
    			'row_num'	=> $counter,
    			'count'		=> $count,
    			'xls_id'	=> $xlsFileId,
    			'status'	=> 0	
    		);
    		
    		if ( isset( $row['D'] ) )
    			 $m_row['source_isbn'] = $row['D'];
    		
    		$mdb_books->insert($m_row);
    		$msg_data = array( 'b' => $seq['seq'], 'n' => htmlspecialchars($book_name,ENT_QUOTES ), 'x' => $xlsFileId, 'a' => $author );
			$this->_bus->triggerXlsRecordParsed($msg_data); 
		}
		$this->log( "end" );
    }

    public function run($args)
    {
    	$data = json_decode($args[0], true);					#var_dump($data);
		$this->log( "start processing ".$data['f'] );
		$file = Yii::app()->params['xls_files']['processing']['path']."/".$data['i'];
		$this->_importExcel($data['f'], (int)$data['i']);
		rename($data['f'],$file);
		$this->log( "end processing ".$file );
		return RET_OK;
    }
    
    private function log($msg)
    {
    	echo "processexcel: $msg";
    }
   
}

?>