<?php 
Yii::import('application.models.User');
Yii::import('application.models.XlsFile');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

class SendMailCommand extends CConsoleCommand
{
	private $_config 				= false;
	private $_bus					= false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 

	public function run($args)
	{
		// Создаем массив с ошибками.
		$constants = get_defined_constants(true);
		
		$json_errors = array();
		foreach ($constants["json"] as $name => $value) {
			if (!strncmp($name, "JSON_ERROR_", 11)) {
				$json_errors[$value] = $name;
			}
		}
		
		$arg_data = json_decode($args[0],  true);
		$xls_id = new MongoId($arg_data['x']);

		$xls_model = XlsFile::model()->findByPk( $xls_id );
		$user_model = User::model()->findById( $xls_model->user_id );
		$user_model->email;		
		$dlink = "http://bib.eksmo.ru/index.php?r=xlsFile/downloadFile&id={$arg_data['x']}";
		$blink = "http://bib.eksmo.ru/index.php?r=xlsFile/view_books&id={$arg_data['x']}";
		$slink = "http://bib.eksmo.ru/index.php?r=xlsFile/view&id={$arg_data['x']}";
		$message = "Ссылка на скачивание файла: {$dlink}\nДанные книг по этому файлу: {$blink}\nСтатистика: {$slink}";
		
		// На случай если какая-то строка письма длиннее 70 символов мы используем wordwrap()
		$message = wordwrap($message, 70);
		
		// Отправляем
		mail($user_model->email, 'Обработан '.$xls_model->orig_name, $message);
		
 		$this->_bus->triggerSendMailComplete( $arg_data );
		
		return RET_OK;
	}
	
	private function log($msg)
	{
		echo "gbclient: $msg";
	}

}

?>