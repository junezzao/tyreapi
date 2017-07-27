@extends('layouts.plain')

@section('title')
    @lang('auth.page_title_login')
@stop

@section('content')
<div class="login-box clearfix">
    <div class="col-md-4 col-sm-12-offset-2 signin-info">
        {!! Html::image("images/arc-black.png", "Logo", array('class'=>'img-responsive center-block login-logo'),env('HTTPS',false)) !!}
        <p>
            We take pride in helping our clients grow, thrive and prosper and we enjoy the relationships we build along the way.
            <br/><br/>&copy; {{ date('Y') }} Hubwire.
        </p>
    </div>
    <div class="login-box-body col-md-8 col-sm-12">
        <p class="login-box-msg">Sign In to your account</p>
        {!! Form::open(array('url' => route('hw.login'), 'id'=>'login-form')) !!}
            {!! Form::hidden('client_id', 'f3d259ddd3ed8ff3843839b') !!}
            {!! Form::hidden('client_secret', '4c7f6f8fa93d59c45502c0ae8c4a95b') !!}
            <div class="form-group has-feedback">
                {!! Form::text('email', null, array('class' => 'form-control', 'placeholder' => 'Username')) !!}
                <div class="error">{{ $errors->first('email') }}</div>
                <span class="glyphicon glyphicon-user form-control-feedback"></span>
            </div> 
            <div class="form-group has-feedback">
                {!! Form::password('password', array('class' => 'form-control', 'placeholder' => 'Password')) !!}
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
            </div>
            
            <div class="icheck">
                <label>
                  <input type="checkbox"> Remember Me
                </label>
            </div>
            <div class="form-group">
                {!! Form::submit('SIGN IN', array('class' => 'signin-btn bg-primary'))!!}
                <a class="forgot-password" id="forgot-password-link">Forgot your password?</a>
            </div> <!-- / .form-actions -->
        {!! Form::close() !!}
    </div><!-- /.login-box-body -->
</div><!-- /.login-box -->

<!-- Password reset form -->
<div class="signin-form col-md-7 col-xs-12" id="password-reset-container">
    <div class="close">&times;</div>
    <div class="signin-text">
        <span>Reset Password</span>
    </div> <!-- / .header -->
    <!-- Form -->
    <p>Fill in your email and a password reset link will be send to your email</p>
    @include('auth.password')
</div>
<!-- / Password reset form -->

@stop

@section('footer_scripts')
<script type="text/javascript">
$(document).ready(function($){
    $("input[name=email]").focus();

    $('#forgot-password-link').click(function(){
        $('#password-reset-container').fadeIn(300);
    });
    $('#password-reset-container .close').click(function () {
        $('#password-reset-container').hide();
    });
});
</script>
@append

