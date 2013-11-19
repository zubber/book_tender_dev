<?php
require_once(dirname(__FILE__).'/../databus/DataBus.php');

class QueueManager extends DataBus
{
	private $_config 				= false;
	private $_mdc_qm				= false;
	protected $day_limit			= false;
	
	function __construct()
	{
		$this->_config = require( dirname(__FILE__).'/../../config/console.php' );
		parent::__construct($this->_config['params'], true);
		$this->day_limit = $this->_config['params']['GBDayLimit'];
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
			"GBCompleteRequest",
			"MKCompleteAllRequests",
			"XlsRecordEmpty",
			"XlsComplete",
			"ProcessStack",
			"SphinxCompleteRequest",
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
	 * Загружает данные qm для файла  
	 * @param number $xls_id id загруженного файла  
	 */
	private function _storage_load($xls_id = 0)
	{
		if ( $xls_id > 0 )
			return $this->_mdc_qm->findOne( array( 'x' => (int)$xls_id ));
		else 
		{
			return $this->_mdc_qm->find();
		}
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
	
	/** НЕ ИСПОЛЬЗУЕТСЯ
	 * Выполняет команду запроса к GB или откладывает ее в стек, если лимит на сегодня превышен.
	 * Используется при событии XlsRecordParsed и обработке стека.
	 * @param number $xls_id id загруженного файла  
	 * @param string $command команда gbclient
	 */
	private function _gb_call($xls_id, $command)
	{
		#проверим сколько запросов сегодня сделали, если лимит, то отложим в стек
		#в коллекции stat_gb документы вида {date:<дата>,cnt:<счетчик>,ts:[timestamp1..timestampN],cmd:[command1..commandN]}
		$mdc = $this->_mdb->selectCollection("stat_gb");
		$criteria	= array( 'date' => date('Y-m-d') );
		$res = $mdc->findOne($criteria);

		if ( $res['cnt'] >= $this->day_limit )
		{
			$this->_storage_save( $xls_id, array( '$addToSet' => array( 'deferred' => $command ) ) );
		}
		else
		{
			$this->_storage_save( $xls_id, array( '$inc' => array( 'gb_call' => 1 ) ) );
			$pid = $this->runCommand($command);
		}
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
				$command = "processexcel '$msg'";
				$this->runCommand( $command );
				break;

			case "XlsGetRecordCount":
				$new_xls = array( 
					'x' => (int)$xls_id,
					'rec' => (int)$data['c'],
					'rec_empty' => 0,
					'gb_call' => 0,
					'begin' => time(),
					'mk_call' => 0,
					'deferred' => array(),
					'is_complete' => 0
				);
				$this->_storage_add($xls_id,$new_xls);
				
				break;
				
			case "XlsRecordEmpty":
				$this->_storage_save($xls_id,array( '$inc' => array( 'rec_empty' => 1 ) ) );
				break;
				
           	case "XlsRecordParsed":
           		$command = "sphinxsearch '$msg'";
           		#$this->_gb_call($xls_id, $command); ДЛЯ РАБОТЫ ЧЕРЕЗ Google Books
           		$this->_storage_save( $xls_id, array( '$inc' => array( 'sphinx_call' => 1 ) ) );
           		$pid = $this->runCommand($command);
           		break;

           		
           	case "XlsComplete":
           		if ( $data['x'] != GENEXCEL_RET_SUCCESS ) break;
           		$command = "sendmail '" . json_encode(array('x'=>$data['x'])) . "'";
           		$pid = $this->runCommand($command);
           		$this->_storage_save($data['x'], array( '$set' => array( 'is_complete' => 1 ) ) );
           		break;
           		
           	case "ProcessStack": 
           		#тут какие могут быть варианты: 1) есть отложенные запросы к GB, значит нужно запустить всю цепочку по-новой;
           		#2) сбойнул процесс или веб-запросы, файл обработан, а счетчики запросов ненулевые - обнуляем, инициируем формирование xls 
           		$curtime = time(); #TODO проверять что файл уже обработан
           		$qm_files = $this->_storage_load();

           		$x_id = false;
				
           		foreach ( $qm_files as $file_data ) 
           		{
           			$x_id = $file_data['x'];
           			#для начала убедимся, что над найденными файлами ничего не совершалось в течение минуты
           			if ( !($file_data['is_complete'] > 0 ) && time() - $file_data['ts_upd'] > 60 && ( $file_data["sphinx_call"] > 0 || $file_data['gb_call'] > 0 || $file_data['mk_call'] > 0 ) )
           			{
         				$this->_storage_save($x_id, array('$set'=>array( 'gb_call' => 0, 'mk_call' => 0 )));
         				$command = "genexcel '" . json_encode(array('x'=>$x_id)) . "'";
         				$pid = $this->runCommand($command);
         				 
           			}
           			#ок, тогда проверим, нет ли отложенных команд, если есть - выполним все и удалим их из стека команд.
           			#попадут снова в стек команды или нет решит _gb_call()
           			elseif ( count($file_data['deferred']) > 0 )
           			{
           				foreach( $file_data['deferred'] as $command )
           				{
	           				$this->_gb_call($x_id, $command);
	           				$this->_storage_save( $x_id, array( '$pop' => array( 'deferred' => -1 ) ) );
           				}
           			}
           		}
           		break;
           		
           	case "SphinxCompleteRequest":
           		$this->_storage_save( $xls_id, array( '$inc' => array( 'sphinx_call' => -1 ) ) );
           		$qm_data = $this->_storage_load( $xls_id );
           		if ( $qm_data['sphinx_call'] <= 0 )
           		{
           			$command = "genexcel '" . json_encode(array('x'=>$data['x'])) . "'";
           			$pid = $this->runCommand($command);
           		}
           		break;           		
           		
#Старые комманды для поиска через гугл книги и МК, сейчас отключены       		
           	case "GBCompleteRequest":
           			$this->_storage_save( $xls_id, array( '$inc' => array( 'gb_call' => -1, 'mk_call' => 1 ) ) );
           			$command = "mkclient '$msg'";
           			$pid = $this->runCommand($command);
           			break;
           			 
           	case "MKCompleteAllRequests":
           			$this->_storage_save( $xls_id, array( '$inc' => array( 'mk_call' => -1 ) ) );
           			$qm_data = $this->_storage_load( $xls_id );
           			if ( $qm_data['mk_call'] <= 0 && $qm_data['gb_call'] <= 0 && count($qm_data["deferred"]) == 0 )
           			{
           				$command = "genexcel '" . json_encode(array('x'=>$data['x'])) . "'";
           				$pid = $this->runCommand($command);
           			}
           			break;
           			 
		}
	}
}
?>