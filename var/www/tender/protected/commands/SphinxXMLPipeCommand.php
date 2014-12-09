<?php 
Yii::import('application.models.Book');
require_once(dirname(__FILE__).'/../extensions/databus/DataBus.php');

class SphinxXMLPipeCommand extends CConsoleCommand
{
	private $_config 				= false;
	private $_bus					= false;
	
	function __construct()
	{
		$this->_bus = new DataBus(Yii::app()->params);
	} 
	
	public function run($args)
	{
		$mdb_conn = new MongoClient( Yii::app()->params['mongo'] );
		$mdb_bc = $mdb_conn->tender->books_catalog;
// 		$query = array('_seq_id'=>array('$lt'=>57300));
// 		$query = array('_seq_id'=>57269);
		$query=array();
		$m_cur = $mdb_bc->find($query);
		print  '<?xml version="1.0" encoding="utf-8"?><sphinx:docset>
	<sphinx:schema>
		<sphinx:field name="name"/>
		<sphinx:field name="author"/>
	</sphinx:schema>'.PHP_EOL;
		foreach( $m_cur as $book)
		{
			if (!isset($book['_seq_id']) || !isset($book['name']) || !is_string($book['name'])) continue;
			print '<sphinx:document id="'.$book['_seq_id'].'">'.PHP_EOL;
			print '	<name><![CDATA[['.$book['name'].']]></name>'.PHP_EOL;
			if ( isset($book['price_authors']) && is_string($book['price_authors']) )
				print '	<author><![CDATA[['.$book['price_authors'].']]></author>'.PHP_EOL;
			print '</sphinx:document>'.PHP_EOL;
		}
		print  '</sphinx:docset>';
	}
}

/*
http://sphinxsearch.com/docs/current.html#xmlpipe2
Пример:
 <?xml version="1.0" encoding="utf-8"?>
 <sphinx:docset>
 
 <sphinx:document id="1234">
 <content>this is the main content <![CDATA[[and this <cdata> entry
 must be handled properly by xml parser lib]]></content>
 <published>1012325463</published>
 <subject>note how field/attr tags can be
 in <b class="red">randomized</b> order</subject>
 <misc>some undeclared element</misc>
 </sphinx:document>
 
 <sphinx:document id="1235">
 <subject>another subject</subject>
 <content>here comes another document, and i am given to understand,
 that in-document field order must not matter, sir</content>
 <published>1012325467</published>
 </sphinx:document>
 
 <!-- ... even more sphinx:document entries here ... -->
 
 </sphinx:docset>
*/
?>