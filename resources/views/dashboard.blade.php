@extends('layouts.master')

@section('title')
    @lang('auth.page_title_dashboard')
@endsection

@section('header_scripts')
<!--link href="{{ asset('css/signin.css', env('HTTPS', false)) }}" rel="stylesheet" type="text/css"-->
@append

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Dashboard - <span class="capitalize">{!! session('user')['user_firstname'] !!}</span>
      </h1>
    </section>

    <!-- Main content -->
    <section class="content">

      <div class="row">
        <div class="col-md-3">
        </div>
    </div>
	</section>
    
@endsection

@section('footer_scripts')
<script type="text/javascript">

</script>
@append

