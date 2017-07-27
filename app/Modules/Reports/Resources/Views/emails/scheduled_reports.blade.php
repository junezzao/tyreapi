<html>
	<p>To whom it may concern,</p>

	<p>The {{ $duration }} {{ $report_type }} Reports for {{ $startdate }} - {{ $enddate }} is ready for viewing.</p>

	<p>You may download the master report here: <a href="{{ $url }}">{{ $url }}</a>.</p>
	<br>
	@if (isset($url2))
		<p>You may download the channel inventory report here: <a href="{{ $url2 }}">{{ $url2 }}</a>.</p>
	@endif
	
	@if(isset($report_list) && !empty($report_list))
		<p>Individual merchant reports can be found here (Note that only merchants with returns activity are reported): </p>
		<ol>
		@foreach($report_list as $merchant => $url)
			<li><stong>{{ $merchant }}</strong> : <a href="{{ $url }}">{{ $url }}</a></li>
		@endforeach
		</ol>
	@endif

	<p>This is a system generated email. Please do not reply.<p>

	<p>Best Regards,<br>
	The Hubwire Arc Enterprise Team<br>
	<img src="{{ $message->embed(asset('images/arc-black.png')) }}" alt="Arc Logo" style="width: 225px; max-height: 80px;" /></p>

	<p>This is a system generated email. Please do not reply.</p>
</html>
