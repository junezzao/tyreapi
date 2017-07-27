<html>
	<head>
		<title>{{ $title }}</title>
	</head>
	<body>
		<p>Dear Ops Team,</p>
		<p>Orders that requires your attention as below:</p>
		<p>
			@foreach($levels as $k=>$v)
			<dl>
				<dd><a href="{{ENV('ARC_URL','http://biz.hubwire.com')}}/orders#filterByLevel={{$k}}">Level {{$k}} (@lang('order-level.level_'.$k)) => <b>{{$v}}</b></a></dd> 
			</dl>
			@endforeach
		</p>
		<p>Please take the necessary action, if any.</p>
		<p>Regards,</p>
		<p>{{ config('mail.from.name') }}</p>
		<p>This is a system generated email. Please do not reply.</p>
	</body>
</html>