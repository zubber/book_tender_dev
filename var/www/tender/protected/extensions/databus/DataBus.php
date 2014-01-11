<?php
	/**
	 * Шина даных всех процессов системы. Знает, какие события есть, но ничего не знает об их данных.
	 * Отвечает за публикацию событий и получение событий в объектах.   
	 */ 
	define('RET_OK', 0 );
	define('RET_ERR_NO_CONNECTION', 1 );
	
	class DataBus
	{
		private 	$_config 	= false;
		private 	$_rh		= false;
		protected 	$_mdb		= false;
		
		function __construct($config = false, $is_daemon = false )
		{
			if ( $config == false )
			{
				$config = require( dirname(__FILE__).'/../../config/console.php' );
				$this->_config = $config['params']['databus'];
			}
			else
				$this->_config = $config;
			$this->is_daemon = $is_daemon;
			$this->_rh = new Redis();
				
			#if ( ! $this->_rh->pconnect( $this->_config['redis']['host'], $this->_config['redis']['port'] ) )
			if ( ! $this->_rh->pconnect( 'localhost', 6379, 60*60*24*180 ) )
			{
				$this->log( "error connect to redis (" . $this->_config['redis']['host'] . ", " . $this->_config['redis']['port'] . ")" );
				$this->_rh = false;
				return false;
			}
			$mdb_conn = new MongoClient( $this->_config['mongo'] );
			$this->_mdb = $mdb_conn->tender;
		}
		
		protected function subscribe(array $channel,$callback)
		{
			if ( $this->_rh )
				$this->_rh->subscribe( $channel, $callback );
			else
			{
				$this->log("can't subscribe ($channel), no connection to redis");
				return RET_ERR_NO_CONNECTION;
			}
				
		}
		
		public function publish( $channel, $msg )
		{
			if ( $r = new Redis() ) { 	#нельзя одновременно использовать pub и sub, поэтому делаем здесь connect-close решение.
				try { 
					#$r->connect( $this->_config['redis']['host'], $this->_config['redis']['port'] );
					$r->connect( 'localhost', 6379 );
					$r->publish( $channel, $msg );
					$r->close();
					if ( ! $r->GetLastError() )
						return true;
				} catch( RedisException $ex ) {
					$this->log( "can't publish ($channel, $msg ), rasing redis exception $ex" );
					return false;
				}
			}
			$this->log("can't publish ($channel, $msg ), no connection to redis");
			return false;
		}
			
		protected function log($msg)
		{
			if ( $this->is_daemon )
				System_Daemon::log(System_Daemon::LOG_INFO, get_class($this).': '.$msg );
			else
				error_log(get_class($this).': '.$msg);
		}
		
		
		/**
		 * Магическая функция обрабатывает преобразует вызовы trigger*() в события шины
		 */
		function __call( $event, $args = array() )
		{
			$data = count( $args ) ? json_encode( $args[0], JSON_UNESCAPED_UNICODE ) : "";
			if ( strpos( $event, 'trigger') === 0 )
			{
				$event = str_replace( "trigger", "", $event );
				$this->publish( $event, $data );
				return true;
			}
		}
	}
?>
