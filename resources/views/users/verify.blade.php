@extends('layouts.master')

@section('title')
	@lang('admin/user.page_title_user_verify')
@stop

@section('content')
	<!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>@lang('admin/user.content_header_user_verify')</h1>
    </section>

    <!-- Main content -->
    <section class="content">
	    <div class="row">
	        <div class="col-xs-12">
	          	<div class="box">
	            	<div class="box-header">
	              		<h3 class="box-title">@lang('admin/user.box_header_user_verify')</h3>
	            	</div><!-- /.box-header -->
	            	<div class="box-body">
	            		{!! Form::open(array('url' => route('hw.users.verify'), 'method' => 'post')) !!}
	            			{!! Form::hidden('user_id', $user_id) !!}
	            			<div class="col-xs-12">
			            		<div class="form-group has-feedback">
		            				<label class="col-xs-2 control-label required" for="password">@lang('admin/user.user_form_label_password')</label>
		            				<div class="col-xs-3">
   										{!! Form::password( 'password', ['class' => 'form-control'] ) !!}
   										<div class="error">{{ $errors->first('password') }}</div>
					                </div>
					            </div>

					            <div class="form-group has-feedback">
		            				<label class="col-xs-2 control-label required" for="password_confirmation">@lang('admin/user.user_form_label_confirm_password')</label>
		            				<div class="col-xs-3">
		            					{!! Form::password( 'password_confirmation', ['class' => 'form-control'] ) !!}
					                	<div class="error">{{ $errors->first('password_confirmation') }}</div>
					                </div>
					            </div>
						    </div>

				         	<div class="col-xs-12">
				         		<div class="form-group pull-right">
					               <button type="submit" class="btn btn-default">@lang('admin/user.button_create_verify_user')</button>
					            </div> <!-- / .form-actions -->
					        </div>
				        {!! Form::close() !!}
	            	</div>
	            </div>
	        </div>
	    </div>
   	</section>
@stop


@section('footer_scripts')
@append