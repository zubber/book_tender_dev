function updateStatTimer()
{
	setTimeout(updateStat, 2000);
}

function updateStat()
{
	jQuery.ajax({
		url:'/index.php?r=xlsFile/AjaxUpdateStat'
		,cache:false
		,data:{xls_file:xlsFile}
		,success:function(html) {
			jQuery("#stat_data").html(html);
			setTimeout(updateStat, 2000);
		 }
	} );
}