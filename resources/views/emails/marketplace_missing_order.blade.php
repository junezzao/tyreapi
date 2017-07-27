<html>
	<head>
		<title>{{ $title }}</title>
	</head>
	<body>
		<p>Dear Tech Team,</p>
		<p><u>Order count</u></p>
		<p>
		<table>
			@foreach($orderCount as $key => $value)
			@if($value > 0)
			<tr>
				<td>{{ $key }}</td>
				<td>{{ $value }}</td>
			</tr>
			@endif
			@endforeach
		</table>
		</p>
		<p>Missing order that you need to attention:<b style="font-size:50px; color: red">{{ $totalMissing }}</b></p>
		<p>
			<table>
			<?php $number=1; ?>
			@foreach($missingOrder as $key => $value)
				<tr>
					<td width="5%">{{ $number++ }}.</td>
					<td width="20%">{{ $value['channel_name'] }}</td>
					<td width="5%">{{ $value['id'] }}</td>
					<td width="15%">{{ $value['date'] }}</td>
					<td width="30%">@if(isset($value['errorMessage'])){{ $value['errorMessage'] }}@endif</td>
					<td width="10%">@if(isset($value['status'])){{ $value['status'] }}@endif</td>
					<td width="15%">@if(isset($value['user'])){{ $value['user'] }}@endif</td>
				</tr>
			@endforeach
			</table>
		</p>
		<p>Best Regards,</p>
		<p>{{ config('mail.from.name') }}</p>
		<p>This is a system generated email. Please do not reply.</p>
	</body>
</html>