<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

define('RET_ERR_NO_FILE', 1 );

class XlsFile2MongoCommand extends TenderConsoleCommand
{
	
    public function run($args)
    {
    	switch($args[0]) {
    		case 'step1': $this->mysql2mongo(); break;
    		case 'step2': $this->copy_stat_xls(); break;
    		case 'step3': $this->copy_books(); break;
    		case 'step4': $this->rename_files_on_disk(); break;
    	}
    }
    
    public function rename_files_on_disk() {
   		$arXls = $this->getXls();
   		$arDirs = array('1.uploaded',  '2.processing',  '3.done');
   		foreach($arDirs as $dir) {
   			$dir = "/var/www/files/{$dir}/";
   			$handle = opendir($dir);
   		    while (false !== ($entry = readdir($handle))) {
   		    	if (!((int)$entry > 0)) 
   		    		continue;
   		
   		    	if (isset($arXls[$entry])) {
   		    		$newname = $arXls[$entry];
   		    		echo "rename {$dir}{$entry} to {$dir}{$newname}\n";
       				rename("{$dir}{$entry}", "{$dir}{$newname}");
   		    	}
   		    	else if (strlen($entry) != 24 && strpos($entry,'.old') === false){
   		    		echo "move old {$dir}{$entry} to {$dir}{$entry}.old\n";
   		    		rename("{$dir}{$entry}", "{$dir}{$entry}.old");
   		    	}
    		}
    		closedir($handle);
   		}
    }
    
    public function copy_books() {
    	$arXls = $this->getXls();
    	$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
    	$arDo = array('update'=>array(),'remove'=>array());
    	$m_cur = $mdb_conn->tender->books->find();
    	foreach($m_cur as $stat) {
    		$criteria = array('xls_id' => $stat['xls_id']);
    		if (!isset($arXls[$stat['xls_id']])) {
    			$mdb_conn->tender->books->remove($criteria);
				$arDo['remove'][] = $criteria;
    		} else {
    			$modify = array('xls_id' => $arXls[$stat['xls_id']]);
				$arDo['update'][] = array('criteria' => $criteria, 'modify' => $modify);
				$mdb_conn->tender->books->update($criteria,array('$set'=>$modify));
    		}
    	}
    }
    
    public function copy_stat_xls() {
    	$arXls = $this->getXls();
    	$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
    	$arDo = array('update'=>array(),'remove'=>array());
    	$m_cur = $mdb_conn->tender->stat_xls->find();
    	foreach($m_cur as $stat) {
    		$criteria = array('xls_id' => $stat['xls_id']);
    		if (!isset($arXls[$stat['xls_id']])) {
    			$mdb_conn->tender->stat_xls->remove($criteria);
				$arDo['remove'][] = $criteria;
    		} else {
    			$modify = array('xls_id' => $arXls[$stat['xls_id']]);
				$arDo['update'][] = array('criteria' => $criteria, 'modify' => $modify);
				$mdb_conn->tender->stat_xls->update($criteria,array('$set'=>$modify));
    		}
    	}
    }
    
    public function getXls() {
    	$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
    	$m_cur = $mdb_conn->tender->xls_file->find();
    	$arXlsFile = array();
    	foreach($m_cur as $xls_file)
    		$arXlsFile[$xls_file['id']] = $xls_file['_id'];
    	return $arXlsFile;
    }
    
    
    public function mysql2mongo() {
    	$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
    	
    	#защита от повторного зауска
    	if($mdb_conn->tender->xls_file->count())
    		return print("Collection xls_file already exists\n");
    	$arUsers = array();
// +-----+---------+--------+-----------+---------------------+--------------------+---------------------+
// | id  | user_id | status | rec_count | cr_date             | orig_name          | end_date            |
// +-----+---------+--------+-----------+---------------------+--------------------+---------------------+
    	$sql2copy = "select * from tbl_xls_file where DATE(cr_date) >= '2014-04-16'";
    	
    	#сохраним пользователей с их ObjectId для DBRef
		$m_cur = $mdb_conn->tender->users->find();
		foreach( $m_cur as $user)
			$arUsers[$user['id']] = $user;
		
		#вытащим все файлы, которые нужно перекачать и сформируем массив для сохранения в монго, включая DBRef на пользователя
		$list= Yii::app()->db->createCommand($sql2copy)->queryAll();
		$rs=array();
		foreach($list as $item){
		    $arItem = array(
		    	'id' => $item['id'], 
		    	'user_id' => $arUsers[$item['user_id']]['_id'],
	    		'rec_count' => $item['rec_count'], 
	    		'cr_date' => $item['cr_date'], 
	    		'orig_name' => $item['orig_name'], 
		    );
		    if ($item['status'])
		    	$arItem['status'] = $item['status'];
		    
		    if ($item['end_date'])
			    $arItem['end_date'] = $item['end_date']; 
			$rs[] = $arItem;
		}
		
// var_dump($rs);
		#сохраним в монго
		foreach( $rs as $item)
			$mdb_conn->tender->xls_file->insert($item);
		
		#привязка по DBRef в xls_file.
		
		return RET_OK;
    }
}

?>