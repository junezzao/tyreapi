<html>
	<head>
		<title>{{ $title }}</title>
	</head>
	<body>
		<p>Dear Ops Team,</p>
		<p>A new note that requires your attention has been created for Order {{$order_id}} by {{$user_name}} as below:</p>
		<p>
			<dl>
				<dd>{{$note_content}}</dd>
			</dl>
		</p>
		<p>Please take the necessary action, if any.</p>
		<p>Regards,</p>
		<p>{{ config('mail.from.name') }}</p>
		<p>This is a system generated email. Please do not reply.</p>
	</body>
</html>