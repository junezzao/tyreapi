<html>
	<p>To whom it may concern,</p>

	<p>The {{ $report_type }} file for {{ $date }} is ready for viewing.</p>

	<p>You may download the file here: <a href="{{ $url }}">{{ $url }}</a>.</p>
	<br>

	<p>This is a system generated email. Please do not reply.<p>

	<p>Best Regards,<br>
	The Hubwire Arc Enterprise Team<br>
	<img src="{{ $message->embed(asset('images/arc-black.png')) }}" alt="Arc Logo" style="width: 225px; max-height: 80px;" /></p>

	<p>This is a system generated email. Please do not reply.</p>
</html>
