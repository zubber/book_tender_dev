function updateStatTimer()
{
	setTimeout(updateStat, 2000);
}

function updateStat()
{
	jQuery.ajax({
		'url':'/tender/index.php?r=xlsFile/AjaxUpdateStat'
		,'cache':false
		,'success':function(html) {
			jQuery("#stat_data").html(html)
		 }
	} );
}