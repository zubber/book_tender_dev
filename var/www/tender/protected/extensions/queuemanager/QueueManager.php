<?php
require_once(dirname(__FILE__).'/../databus/DataBus.php');

class QueueManager extends DataBus
{
	private $_config 				= false;
	private $_mdc_qm				= false;
	
	function __construct()
	{
		$this->_config = require( dirname(__FILE__).'/../../config/console.php' );
		parent::__construct($this->_config['params'], true);
		$this->_mdc_qm = $this->_mdb->selectCollection("qm");
	}
	
	/**
	 * Что слушаем в шине, вызывается дмеонизирующим класс скриптом 
	 */
	public function listen()
	{
		return $this->subscribe(array(
			"XlsUpload",
			"XlsGetRecordCount",
			"XlsRecordParsed",
			"XlsRecordEmpty",
			"XlsComplete",
			"ProcessStack",
// 			"SphinxCompleteRequest",
			"GenerateXLS"
			), array( $this, 'onEvent' ));
	}
	
	/**
	 * Асинхронный запуск shell-команд 
 	 * @param string $cmd
	 */
	
	public function runCommand($cmd)
	{
		$path = $this->_config['basePath'];
		$command = "cd $path && nohup ./yiic $cmd >> /var/log/queue_manager.log 2>> /var/log/queue_manager.log &";  
		$this->log( "executing $command" );
		return shell_exec( $command ); #
	}
	
	
	/**
	 * обавляет в монго новый документ об обрабатываемом queue_manager файле ( счетчики запросов, отложенные команды ), дабы в случае сбоя все это отработать
	 * @param array $data дата для монго
	 */
	private function _storage_add($xls_id, $data = array())
	{
		$this->_mdc_qm->insert( $data );
		$this->_ts_update($xls_id);
	}
	
	/**
	 * Сохраняет в монго состояние обработки файла
	 * @param number $xls_id id загруженного файла  
	 * @param array $data дата для монго
	 */
	private function _storage_save($xls_id, $data = array())
	{
		$this->_mdc_qm->update( array( 'x' => (int)$xls_id ), $data);
		$this->_ts_update($xls_id);
	}
	
	/**
	 * Удаляет данные qm для файла, должен вызываться когда полностью закончена обработка файла.
	 * @param number $xls_id id загруженного файла
	 */
	private function _storage_erase($xls_id = 0)
	{
		$this->_mdc_qm->remove( array( 'x' => (int)$xls_id ), array('justOne' => true) );
	}
	
	/**
	 * Обновляет время последней операции над документом. 
	 * @param number $xls_id id загруженного файла  
	 */
	private function _ts_update($xls_id = 0)
	{
		$this->_mdc_qm->update( array( 'x' => (int)$xls_id ), array( '$set' => array( 'ts_upd' => time() ) ) );
	}
	
	/**
	 * Обработчик событий в шине 
	 * @param unknown $redis на всякий слкчай объект редиса
	 * @param string $chan событие
	 * @param string $msg данные события в json
	 */
	public function onEvent( $redis, $chan, $msg )
	{
		$data = json_decode($msg, true);
		$xls_id = $data['x'];
		switch($chan)
		{
			case "XlsUpload":
				$obj		= "xls";
				$op 		= "insert";
				$data 		= array(
						"xls_id" 		=> (int)$data['i'],
						"rows_total" 	=> 0,
						"rows_empty" 	=> 0,
						"rows_tasked" => 0,
						"sphinx_stat" => array(
							"c"		=> 0,
							"c_10"	=> 0,
							"c_11"	=> 0,
							"f_0"		=> 0,
							"f_1"		=> 0,
							"f_2"		=> 0
						),						
						'is_complete' 	=> 0,
						'cdate'			=> time(),
						'edate' 		=> 0,
						
				);
				$command = "processexcel '$msg'";				
				$this->runCommand( $command );
				break;

			case "XlsGetRecordCount":
				$new_xls = array( 
					'x' => (int)$xls_id,
					'rec' => (int)$data['c'],
					'rec_empty' => 0,
					'begin' => time(),
					'is_complete' => 0
				);
				$this->_storage_add($xls_id,$new_xls);
				
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( "xls_id" => (int)$data['x'] );
				$data 		= array( '$set' => array( "rows_total" 	=> (int)$data['c'] ) );
				break;
				
			case "XlsRecordEmpty":
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( "xls_id" => (int)$data['x'] );
				$data 		= array( '$inc' => array( "rows_empty" 	=> 1 ) );
				
				$this->_storage_save($xls_id,array( '$inc' => array( 'rec_empty' => 1 ) ) );
				break;
				
           	case "XlsRecordParsed":
           		$command = "sphinxsearch '$msg'";
           		$this->_storage_save( $xls_id, array( '$inc' => array( 'sphinx_call' => 1 ) ) );
           		$pid = $this->runCommand($command);
           		
           		$obj		= "xls";
           		$op			= "update";
           		$criteria	= array( 'xls_id' => (int)$data['x'] );
           		$data 		= array( '$inc' => array( "rows_tasked" => 1 ) );
           		break;

           		
           	case "XlsComplete":
           		if ( $data['s'] == GENEXCEL_RET_SUCCESS ) 
           		{
	           		$command = "sendmail '" . json_encode(array('x'=>$data['x'])) . "'";
	           		$pid = $this->runCommand($command);
           			$this->_storage_save($data['x'], array( '$set' => array( 'is_complete' => 3 ) ) );
           			$obj		= "xls";
           			$op			= "update";
           			$criteria	= array( 'xls_id' => (int)$data['x'] );
           			$data		= array( '$set' => array('is_complete' => 3, 'edate' => time()));
           		}
           		break;
           		
           	case "ProcessStack": 
           		#тут какие могут быть варианты: 1) есть отложенные запросы к GB, значит нужно запустить всю цепочку по-новой - уже нет этого;
           		#2) сбойнул процесс или веб-запросы, файл обработан, а счетчики запросов ненулевые - обнуляем, инициируем формирование xls 
           		$curtime = time(); #TODO проверять что файл уже обработан
           		$qm_files = $this->_mdc_qm->find(array( 'is_complete' => 0 ));
           		 
           		$x_id = false;
           		foreach ( $qm_files as $file_data ) 
           		{
           			$x_id = $file_data['x'];
           			#для начала убедимся, что над найденными файлами ничего не совершалось в течение минуты
           			$is_complete = $file_data['is_complete'];
           			if ( $is_complete != 3 && time() - $file_data['ts_upd'] > 60 )
           			{
           				if ($is_complete < 2) {
         				    $command = "genexcel '" . json_encode(array('x'=>$x_id)) . "'";
         				    $pid = $this->runCommand($command);
         				    $is_complete = 2;
         				    $this->_storage_save($x_id, array('$set'=>array('is_complete' => $is_complete)));
         				}
         				if ($is_complete == 2) {
	           			    $command = "sendmail '" . json_encode(array('x'=>$data['x'])) . "'";
	           			    $pid = $this->runCommand($command);
	           			    $is_complete++;
           				    $this->_storage_save($data['x'], array( '$set' => array( 'is_complete' => $is_complete ) ) );
			           	    $obj		= "xls";
           				    $op			= "update";
           				    $criteria	= array( 'xls_id' => (int)$data['x'] );
           				    $data		= array( '$set' => array('is_complete' => $is_complete, 'edate' => time()));
         				}
           			}
           		}
           		break;
           		
//            	case "SphinxCompleteRequest":
//            		$this->_storage_save( $xls_id, array( '$inc' => array( 'sphinx_call' => -1 ) ) );
//            		$qm_data = $this->_mdc_qm->findOne(array( 'x' => (int)$xls_id ));
//            		if ( $qm_data && $qm_data['sphinx_call'] <= 0 )
//            		{
//            			$command = "genexcel '" . json_encode(array('x'=>$data['x'])) . "'";
//            			$pid = $this->runCommand($command);
//            		}
//            		$obj		= "xls";
//            		$op			= "update";
//            		$criteria	= array( 'xls_id' => (int)$data['x'] );
//            		$data 		= array( '$inc' => array("sphinx_stat.c" => 1, "sphinx_stat.c_".$data['s'] => 1, "sphinx_stat.f_".$data['f'] => 1 ));
//            		break;       
       		case "GenerateXLS":
   			$command = "genexcel '" . json_encode(array('x'=>$xls_id)) . "'";
       			$pid = $this->runCommand($command);
       			$this->_storage_save($xls_id, array('$set'=>array('is_complete' => 2)));
       			break;
		}
		$mdc = $this->_mdb->selectCollection("stat_$obj");
		switch($op)
		{
			case "insert" :
				$mdc->insert( $data );
				break;
			case "update" :
				$mdc->update( $criteria, $data );
				break;
			case "upsert" :
				$mdc->update( $criteria, $data, array("upsert" => true) );
				break;
		}
	}
}
?>
