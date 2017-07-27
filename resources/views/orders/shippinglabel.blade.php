<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <script src="{{ asset('js/jquery-3.1.1.min.js', env('HTTPS', false)) }}" type="text/javascript"></script>
    <style type="text/css">
        body { font-family: "Open Sans", Arial, sans-serif; }
        table { font-size:12px; }
            th { color: white; -webkit-print-color-adjust: exact; }
        tr.breaker td { padding:15px 7px; }
        tr td.title { padding: 5px 7px; }
        tr.spanbreaker td { padding: 5px 7px; }
        .title { align:center; font-size:13px; font-weight:bold; }
        .odd { background-color: #ececec; }
        .even { background-color: #f8f8f8; }
        hr { margin: 0 auto; }
        .channel_type { border: 1px solid #999; border-radius: 3px; background-color: #f8f8f8; padding: 3px; font-size: 11px; }
        .small { font-size: 9px; }
        .ita { font-style: italic; }
        .page-break{ page-break-after: always; }
        .fleft-fix{ max-width:100% !important; }
    </style>
    <script type="text/javascript">
        function printpage() {
            window.print();
        }
    </script>
</head>
<!--body onload="printpage();"-->
<body>
    <div class="page-break">
	<?php
	if(!empty($documents)){
	    $count = 0 ;
		foreach($documents as $document){
		   if($count > 0 ) echo('<div style="page-break-after:always"></div>');
	        echo $document->document_content;
	        $count++;
		}
	} 
	else{
		echo '<p> No document(s) found!</p>';
	}
?>
 <script type="text/javascript">
 jQuery(document).ready(function(){

    $('div').each(function(i){
        var width = Math.ceil( 100 * parseFloat($(this).css('width')) / parseFloat($(this).parent().css('width')) ) + '%';

        if($(this).parent().width() <= ($(this).width() + parseInt($(this).css('margin-left'), 10) + parseInt($(this).css('padding-left'), 10))){
            // $(this).addClass('fleft-fix');
            $(this).css('maxWidth', $(this).parent().width() - parseInt($(this).css('margin-left'), 10) - parseInt($(this).css('padding-left'), 10) + 'px');
        }
    });

    print();
 
 });
 
 </script>
</body>
</html>
