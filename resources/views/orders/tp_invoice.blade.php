<?php
	if(!empty($documents)){
	    $count = 0 ;
		foreach($documents as $document){
		   if($count > 0 ) echo('<div style="page-break-after:always"></div>');
		    $content  = str_replace('<body>','<body class="container">', $document->document_content);
	        $document->document_content = $content;
	        echo str_replace('<div class="logo">','<div class="logo"></div>',$document->document_content);
	        $count++;
		}
	}
	else{
		echo '<p> No document(s) found!</p>';
	}
?>
 <script type="text/javascript">print();</script>
