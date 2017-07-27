<html>
	<p>Dear Ops Team,</p>

	<p>There are pending failed orders that require your attention.</p>

	<p>Details as follows:<br>
		<ol>
			@foreach($channels as $channelName => $channel)
				<li>
					<b>{{ $channelName }}</b> -
					@foreach($channel as $status => $total)
						{{ ' ' . $status . ' [' . $total . ']' }}
					@endforeach
				</li>
			@endforeach
		</ol>
	</p>

	<p>Please take the necessary action, if any.</p>

	<p>Best Regards,<br>
	{{ config('mail.from.name') }}<br/><br/>
	This is a system generated email. Please do not reply.</p>
</html>