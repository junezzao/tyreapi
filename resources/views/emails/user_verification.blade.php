@extends('layouts.email')

@section('content')
<p>Hi {{ ucwords($recipientName) }},</p>

	<p>Welcome to <span style="color:#8BC34A"><strong>{{ config('app.name') }}</strong></span>!</p>

	<p>Thank you for joining us! We are looking forward to assisting you in your exciting journey ahead!</p>

	<p>Below are your account details:<br>
		<ul style="list-style-type:none">
			<li>Email: {{ $email }}</li>
		</ul></p>
	<p>Please click <a href="{{ $actionUrl }}">here</a> to activate your account. You will be prompted to set your password upon activation.</p>


	<p><small class="help-text">If you did not register for an account with {{ config('app.name') }}, kindly report the incident by contacting <a href="mailto:{{ config('mail.tech_support.address') }}" alt="{{ config('mail.tech_support.name') }}">{{ config('mail.tech_support.address') }}</a>.</small></p>

@endsection