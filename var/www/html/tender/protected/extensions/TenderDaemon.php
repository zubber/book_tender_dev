<?php
require_once 'System/Daemon.php';
class TenderDaemon
{
	protected $_runmode	= array(	'no-daemon' => false, 'help' => false );
	protected $_dc		= false; 
	protected $_dc_name	= false;
	
	function __construct($daemonizeClass, $options)
	{
		$this->_dc_name = $daemonizeClass;
		$options_all = array_merge( $options, array(
			'appDir' => dirname(__FILE__),
			'sysMaxExecutionTime' => '0',
			'sysMaxInputTime' => '0',
		));
		System_Daemon::setOptions($options_all);
	}
	
	public function run($argv = false)
	{

		// Scan command line attributes for allowed arguments
		foreach ($argv as $k=>$arg) {
			if (substr($arg, 0, 2) == '--' && isset($runmode[substr($arg, 2)])) {
				$this->_runmode[substr($arg, 2)] = true;
			}
		}
		
		// Help mode. Shows allowed argumentents and quit directly
		if ($this->_runmode['help'] == true) {
			echo 'Usage: '.$argv[0].' [runmode]' . "\n";
			echo 'Available runmodes:' . "\n";
			foreach ($this->_runmode as $runmod=>$val) {
				echo ' --'.$runmod . "\n";
			}
			die();
		}
		
		System_Daemon::setSigHandler(SIGTERM, 
			function($signal)
			{
				if ($signal === SIGTERM) {
					System_Daemon::log("Recieving $signal, normal shutdown.");
					System_Daemon::stop();
					if ( $this->_dc )
					{
						$tr_offline = "trigger" . $this->_dc_name . "Offline";
						$this->_dc->$tr_offline(array('p'=>posix_getpid()));
					}
				}
			}
		
		);
		
		if (!$this->_runmode['no-daemon'])
			System_Daemon::start();

		System_Daemon::log(System_Daemon::LOG_INFO, "Daemon started");
		
		start:
		$m = false;
		$tr_online = "trigger" . $this->_dc_name . "Online";
		try{
			$this->_dc = new $this->_dc_name();
			$this->_dc->$tr_online(array('p'=>posix_getpid()));
			do {
				$retval = $this->_dc->listen();
			} while( !System_Daemon::isDying() && $retval == 0 ); #
		}
		catch (Exception $e) {
			#Какие эксепшены:
			#'RedisException' with message 'read error on connection'
			unset( $this->_dc );
			goto start;
		}
		if ( $retval == RET_ERR_NO_COONECTION )
		{
			System_Daemon::iterate(2);		#пытаемся переподключиться
			System_Daemon::log(System_Daemon::LOG_INFO, "Reconnecting..");
			goto start;
		}
		if ( $this->_dc )
		{
			$tr_offline = "trigger" . $this->_dc_name . "Offline";
			$this->_dc->$tr_offline(array('p'=>posix_getpid()));
		}

		System_Daemon::stop();
		System_Daemon::log(System_Daemon::LOG_INFO,'Exit');
	}
	
}

	
?>