@extends('layouts.email')

@section('content')
<p>Hi {{ ucwords($recipientName) }},</p>

	<p>Welcome to <span style="color:#ffcc05"><strong>{{ config('app.name') }}</strong></span>!</p>

	<p>Thank you for joining us! We are looking forward to assisting you in your exciting journey ahead!</p>

	<p>If you need any help in navigating the site, you may refer to our <a href="#">Frequently Asked Questions</a> section or drop us a message at <a href="mailto:{{ config('mail.tech_support.address') }}" alt="{{ config('mail.tech_support.name') }}">{{ config('mail.tech_support.address') }}</a>.</p>
	<p>Below are your account details:<br>
		<ul style="list-style-type:none">
			<li>Email: {{ $email }}</li>
		</ul></p>
	<p>Please click <a href="{{ $actionUrl }}">here</a> to activate your account. You will be prompt to set your password upon activation.</p>


	<p><small class="help-text">If you did not register for an account with {{ config('app.name') }}, kindly report the incident by contacting <a href="mailto:{{ config('mail.tech_support.address') }}" alt="{{ config('mail.tech_support.name') }}">{{ config('mail.tech_support.address') }}</a>.</small></p>

@endsection