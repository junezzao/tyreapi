{!! Form::open(array('url' => route('hw.password.forgot'), 'id' => 'password-reset-form')) !!}
    <div class="form-group has-feedback">
        {!! Form::text('email', '', array('class' => 'form-control', 'placeholder' => 'Username', 'placeholder' => 'Enter your email')) !!}
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
    </div> <!-- / Email -->
    <div class="form-group">
        {!! Form::submit('Send Password Reset Link', array('class' => 'btn btn-default')) !!}
    </div> <!-- / .form-actions -->
{!! Form::close() !!}