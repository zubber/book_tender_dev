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
// 			"XlsUpload",
// 			"XlsGetRecordCount",
// 			"XlsRecordParsed",
// 			"XlsRecordEmpty",
			"SphinxCompleteRequest",
// 			"ProcessStack",
// 			"XlsComplete"
		), array( $this, 'onEvent' ));
	}
	
	public function onEvent( $redis, $chan, $msg )
	{

		$data = json_decode($msg, true);
		$xls_id = (int)$data['x'];
		switch($chan)
		{
/*			case "XlsUpload":
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
				break;
				
			case "XlsRecordEmpty":
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( "xls_id" => (int)$data['x'] );
				$data 		= array( '$inc' => array( "rows_empty" 	=> 1 ) );
				break;

			case "XlsGetRecordCount":
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( "xls_id" => (int)$data['x'] );
				$data 		= array( '$set' => array( "rows_total" 	=> (int)$data['c'] ) );
				break;
			
			case "XlsRecordParsed":
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( 'xls_id' => (int)$data['x'] );
				$data 		= array( '$inc' => array( "rows_tasked" => 1 ) );
				break;
*/
			case "SphinxCompleteRequest":
// 				$obj		= "xls";
// 				$op			= "update";
// 				$criteria	= array( 'xls_id' => (int)$data['x'] );
// 				$data 		= array( '$inc' => array("sphinx_stat.c" => 1, "sphinx_stat.c_".$data['s'] => 1, "sphinx_stat.f_".$data['f'] => 1 ));
// 				break;
				
				$query = array( 'x' => $xls_id );
				$modify = array( '$inc' => array( 'sphinx_call' => -1 ) );
				$projection = array( 'sphinx_call' => 1);
				$qm_data = $this->_mdc_qm->findAndModify($query,$modify,$projection);
				if ( $qm_data && $qm_data['sphinx_call'] <= 0 )
				{
					$this->_bus->triggerGenerateXLS(json_encode(array('x'=>$xls_id))); 
				}
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( 'xls_id' => (int)$data['x'] );
				$data 		= array( '$inc' => array("sphinx_stat.c" => 1, "sphinx_stat.c_".$data['s'] => 1, "sphinx_stat.f_".$data['f'] => 1 ));
				break;
				
				
/*			
			case "XlsComplete":
				$obj		= "xls";
				$op			= "update";
				$criteria	= array( 'xls_id' => (int)$data['x'] );
				$data		= array( '$set' => array('is_complete' => 1, 'edate' => time()));
				break;
*/
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