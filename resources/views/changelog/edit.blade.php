@extends('layouts.master')

@section('title')
    @lang('changelog.page_title_changelog_edit')
@stop

@section('header_scripts')

@append

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>@lang('changelog.content_header_changelog')</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title">
                            @lang('changelog.box_header_edit')
                        </h3>
                    </div><!-- /.box-header -->
                    <div class="box-body">
                        {!! Form::open(array('url' => route('1.0.hw.changelog.update', $changelog->id), 'method' => 'PUT')) !!}
                            <div class="col-xs-12">
                                <div class="form-group has-feedback">
                                    <label class="col-xs-2 control-label required" for="title">@lang('changelog.form_label_title')</label>
                                    <div class="col-xs-10">
                                        {!! Form::text('title', $changelog->title, array('class'=>'form-control', 'placeholder'=>'Title')) !!}
                                        <div class="error">{{ $errors->first('title') }}</div>
                                    </div>
                                </div>

                                <div class="form-group has-feedback">
                                    <label class="col-xs-2 control-label required" for="title">@lang('changelog.type')</label>
                                    <div class="col-xs-10">
                                        {!! Form::select('type', $changelogType, $changelog->type, array('class'=>'form-control', 'placeholder'=>'Type')) !!}
                                        <div class="error">{{ $errors->first('type') }}</div>
                                    </div>
                                </div>

                                <div class="form-group has-feedback">
                                    <label class="col-xs-2 control-label required" for="content">@lang('changelog.form_label_content')</label>
                                    <div class="col-xs-10">
                                        {!! Form::textarea('content', $changelog->content, array('id'=>'content','class'=>'form-control')) !!}
                                        <div class="error">{{ $errors->first('content') }}</div>
                                    </div>
                                </div>         
                            </div>

                            <div class="col-xs-12">
                                <div class="form-group pull-right">
                                   <button type="submit" class="btn btn-primary">@lang('changelog.button_update_changelog')</button>
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
<!-- DataTables -->
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js', env('HTTPS',false) ) }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap.min.js', env('HTTPS',false) ) }}"></script>
<script src="{{ asset('plugins/summernote/summernote.min.js', env('HTTPS',false) ) }}"></script>
<link href="{{ asset('plugins/datatables/dataTables.bootstrap.css', env('HTTPS',false) ) }}" rel="stylesheet" type="text/css">
<link href="{{ asset('plugins/summernote/summernote.css', env('HTTPS',false) ) }}" rel="stylesheet" type="text/css">
<script type="text/javascript">
jQuery(document).ready(function(){
    $('#content').summernote({
        minHeight: 300,             
        focus: true,                
    });
});
</script>
@append
