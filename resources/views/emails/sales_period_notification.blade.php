<html>
	<head>
		<title>{{ $title }}</title>
	</head>
	<body>
		<p>Dear Sir/Madam,</p>
		<p>SKUs that require your attention as below:</p>
		
		@if(!empty($starting))
		<br>
		<p><b>Sales Period Starting Tomorrow ({{$tomorrow}})</b></p>
		<table>
			<thead>
				<tr>
				<td><u>Brand Name</u></td>
				<td><u>Number Of Products</u></td>
				<td><u>Number Of SKUs</u></td>
				</tr>
			</thead>
			<tbody>
				@foreach($starting as $start)
				<tr>
					<td>{{$start->brand_name}}</td>
					<td align="right">{{number_format($start->products,0,",","")}}</td>
					<td align="right">{{number_format($start->skus,0,",","")}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		@if(!empty($expiring))
		<br>
		<p><b>Sales Period Expiring End of Today ({{$today}})</b></p>
		<table>
			<thead>
				<tr>
				<td><u>Brand Name</u></td>
				<td><u>Number Of Products</u></td>
				<td><u>Number Of SKUs</u></td>
				</tr>
			</thead>
			<tbody>
				@foreach($expiring as $expire)
				<tr>
					<td>{{$expire->brand_name}}</td>
					<td align="right">{{number_format($expire->products,0,",","")}}</td>
					<td align="right">{{number_format($expire->skus,0,",","")}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		<br>
		<p>Listing Price / Retail Price will be auto synced to marketplaces at midnight, according to sales start / end date.</p>
		<p>Kindly report to techsupport@hubwire.com if any failed "updatePrice" syncs found. Thank you.</p>
		<p>Regards,</p>
		<p>{{ config('mail.from.name') }}</p>
		<p>This is a system generated email. Please do not reply.</p>
	</body>
</html>