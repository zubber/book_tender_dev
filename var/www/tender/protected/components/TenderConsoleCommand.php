<?php 
Yii::import('application.models.Book');
class TenderConsoleCommand extends CConsoleCommand {
	static protected $_blocks = array();
	static protected $_out = '';
	
	public function log($msg)
	{
		$callers=debug_backtrace();
		$caller = $callers[1]['function'];
		echo get_called_class().":{$caller}:{$msg}\n";
	}
	
	static public function beginProfile($name)
	{
		if( !Yii::app()->params['enable_profiling'] ) return;
		array_push(self::$_blocks,array('name'=>$name,'start'=>microtime(true)));
	}
	
	static public function endProfile($name)
	{
		if( !Yii::app()->params['enable_profiling'] ) return;
		$last = end(self::$_blocks);
		if ($last['name']!=$name)
			return false;
		$last = array_pop(self::$_blocks);
		$callers=debug_backtrace();
		$caller = $callers[1]['function'];
		$msg = get_called_class().":{$caller}:{$name}:".(round(microtime(true)-$last['start'],3,PHP_ROUND_HALF_UP));
		self::$_out.="\n$msg";
		return $msg;
	}

	public function afterAction() {
		if( !Yii::app()->params['enable_profiling'] ) return;
		file_put_contents('/tmp/tenderProfiler.log',self::$_out,FILE_APPEND);
	}
}
?>