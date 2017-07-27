<html>
	<head>
		<title>{{ $title }}</title>
	</head>
	<body>
		<p>Dear {{$channel}},</p>
		<p>Orders that requires your attention as below:</p>
		<p>
			Your webhook for '{{$webhook['topic']}}' at {{$webhook['address']}} has been returning
			failure responses for {{$limit}} times webhook attempts.
		</p>
		<p> {{$response}} </p>
		<p>
			This webhook has been removed. You may retrieve it by calling relevent Hubwire Storefront
			API.
		</p>
		<p>Please take the necessary action, if any.</p>
		<p>Regards,</p>
		<p>{{ config('mail.from.name') }}</p>
		<p>This is a system generated email. Please do not reply.</p>
	</body>
</html>