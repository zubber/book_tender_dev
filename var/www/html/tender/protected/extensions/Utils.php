<?php 

function get_xml($request, $try_count = 1)
{
	$errors = "Errors: \n";
	libxml_use_internal_errors(true);
	_log("Requesting $request, try #$try_count" );
	$is_errors = false;
	$str = file_get_contents($request);
	#$str = preg_replace_callback('/<name>(.*)<\/name>/', 'filter_xml', $str);
	
	$ret = simplexml_load_string( $str, 'SimpleXMLElement', LIBXML_NOCDATA );

	foreach (libxml_get_errors() as $error)
	{
		$is_errors = true;
		$errors .= "on try $try_count error parsing $request \n";
	}
	if ( $is_errors === false)
		return $ret;
				
	libxml_clear_errors();
	
	$try_count++;
	if ( $try_count <= 3 )
	{
		sleep(5);
		$ret = get_xml($request, $try_count );
	}
	else
	{
		_warn("Error requesting $request, 3 times trying.");
		return true;
	}
	return $ret;
}

function filter_xml($matches)
{
	return "<name>" . trim(htmlspecialchars($matches[1], ENT_COMPAT | ENT_XML1)) . "</name>";
}

function _log($msg)
{
	print "$msg\n";
}

function _warn($msg)
{
	print "WARNING: $msg\n";
}
?>