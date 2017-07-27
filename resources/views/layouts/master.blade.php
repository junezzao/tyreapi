<!DOCTYPE html>
<html>
	<head>
	    @include('includes.head')
	</head>	
	<body class="hold-transition skin-black-light sidebar-mini">
		<div class="wrapper">
			<header class="main-header">
		        @include('includes.header')
		    </header>

		    <!-- Left side column. contains the logo and sidebar -->
			<aside class="main-sidebar">
				@include('includes.nav-sidebar')
			</aside>

		    <!-- Content Wrapper. Contains page content -->
  			<div class="content-wrapper">
  				@include('flash::message')
		        @yield('content')
		    </div>

		    <!-- <footer class="main-footer">
		    </footer> -->
		</div>
		@include('includes.footer_scripts')
	</body>
</html>