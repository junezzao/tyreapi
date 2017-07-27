@extends('layouts.email')

@section('content')
<p>Hi {{ ucwords($recipientName) }},</p>

	<p>You are receiving this email because we received a password reset request for your account.</p>
	<p>Please click <a href="{{ $actionUrl }}">here</a> to reset your password.</p>
	<p>If you did not request a password reset, no further action is required.</p>
@endsection