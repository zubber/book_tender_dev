<?php
require_once(dirname(__FILE__).'/../databus/DataBus.php');

class StatManager extends DataBus
{
	private $_config 				= false;
	private $_mdc_qm				= false;
	
	function __construct()
	{
		$this->_config = require( dirname(__FILE__).'/../../config/console.php' );
		parent::__construct($this->_config['params'], true);
		$this->_mdc_qm = $this->_mdb->qm;

	}
	
	public function listen()
	{
		return $this->subscribe(array(
			"SphinxCompleteRequest",
		), array( $this, 'onEvent' ));
	}
	
	public function onEvent( $redis, $chan, $msg )
	{

		$data = json_decode($msg, true);
		$xls_id = new MongoId($data['x']);
		switch($chan)
		{
			case "SphinxCompleteRequest":
				$query = array( 'x' => $xls_id );
				$modify = array( '$inc' => array( 'sphinx_call' => -1 ) );
				$projection = array( 'sphinx_call' => 1);
				$criteria	= array( 'xls_id' =>$xls_id );
								
				$qm_data = $this->_mdc_qm->findAndModify($query,$modify,$projection,array('new'=>true)); 
				$stat_xls_data = array( '$inc' => array("sphinx_stat.c" => 1, "sphinx_stat.c_".$data['s'] => 1, "sphinx_stat.f_".$data['f'] => 1 ));
				$stat_data = $this->_mdb->stat_xls->findAndModify($criteria,$stat_xls_data,null,array('new'=>true));
				
				if ( $qm_data && $stat_data && $qm_data['sphinx_call'] <= 0 && $stat_data['rows_total'] == $stat_data['sphinx_stat']['c'] ) {
					$this->triggerGenerateXLS(array('x'=>$data['x'])); 
				}
				break;
		}
	}
}
?>