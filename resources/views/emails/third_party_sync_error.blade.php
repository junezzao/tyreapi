<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>ERROR ON SERVER</title>
	</head>
	<body>
		<p>Error On Server </p>
		<p>Invalid Response Exception </p>
		<p> 
			<?php
				unset($data["email"]);
				echo "<pre>"; 
				print_r($data);
				echo "</pre>";
			?>
		</p>
	</body>
</html>
