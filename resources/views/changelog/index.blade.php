@extends('layouts.master')

@section('title')
    @lang('changelog.page_title_changelog_list')
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
                            @lang('changelog.box_header_index')
                        </h3>
                        @if($admin->can('create.changelog'))
	                        <a href="{{route('1.0.hw.changelog.create')}}" class="btn btn-default pull-right">
	                            @lang('changelog.button_add_new_changelog')
	                        </a>
                        @endif
                    </div><!-- /.box-header -->
                    <div class="box-body">
                        <table id="pages_list" class="table table-condensed">
							<thead>
								 <tr>
								 	<th>@lang('changelog.form_label_title')</th>
                                    <th>@lang('changelog.type')</th>
								 	<th>@lang('changelog.form_label_content')</th>
								 	<th>@lang('changelog.label_date')</th>
								 	<th>@lang('changelog.label_actions')</th>
								 </tr>
							</thead>
							<tbody>
								@if(!empty($changelogs))
									@foreach($changelogs as $changelog)
										<tr>
										 	<td>{!! $changelog['title'] !!}</td>
                                            <td>{!! $changelog['type'] !!}</td>
										 	<td>{!! $changelog['content'] !!}</td>
										 	<td>{!! $changelog['created_at'] !!}</td>
										 	<td>{!! $changelog['actions'] !!}</td>
										</tr>
									@endforeach
								@endif	
							</tbody>
						</table>
                    </div>
                </div>
            </div>
        </div>
        <div class="html_to_replace"></div>
    </section>
@stop

@section('footer_scripts')
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js', env('HTTPS', false)) }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap.min.js', env('HTTPS', false)) }}"></script>
<link href="{{ asset('plugins/datatables/dataTables.bootstrap.css', env('HTTPS', false)) }}" rel="stylesheet" type="text/css">
<script type="text/javascript">
jQuery(document).ready(function(){
    var table = $('#pages_list').DataTable({
    	"order": [[ 3, 'desc' ]],
    	"columnDefs": [
    		{ "width": "10%", "targets": 0 },
            { "width": "10%", "targets": 1 },
			{ "width": "55%", "targets": 2 },
			{ "width": "15%", "targets": 3 },
			{ "width": "10%", "targets": 4 },
		]
    });

    $(document).on('click', '.confirmation', function (e) {
		//e.preventDefault();
        return confirm('Are you sure you want to delete this changelog?');
    });

    /*var actions_col = table.column(3);
    if ("{{$admin->is('administrator|superadministrator')}}" == 1) {
        actions_col.visible(true);
    }
    else {
        actions_col.visible(false);
    }*/
});
</script>
@append
