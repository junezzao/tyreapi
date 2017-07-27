@section('header_scripts')
@append

<!-- Logo -->
<a href="/hwlogin/home" class="logo" style="background-color:#FFF!important">
  <!-- mini logo for sidebar mini 50x50 pixels -->
  <span class="logo-mini">{!! Html::image("images/hubwire-logo-mini.png", "Logo", array('id'=>'header-logo-mini'),env('HTTPS',false)) !!}</span>
  <!-- logo for regular state and mobile devices -->
  <span class="logo-lg">{!! Html::image("images/arc-black.png", "Logo", array('id'=>'header-logo', 'class'=>'img-responsive center-block login-logo'),env('HTTPS',false)) !!}</span>
</a>
<!-- Header Navbar: style can be found in header.less -->
<nav class="navbar navbar-static-top" role="navigation">
  <!-- Sidebar toggle button-->
  <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
    <span class="sr-only">Toggle navigation</span>
    <span class="icon-bar"></span>
    <span class="icon-bar"></span>
    <span class="icon-bar"></span>
  </a>
  
  <div class="navbar-custom-menu">
    <ul class="nav navbar-nav">
      <!-- User Account: style can be found in dropdown.less -->
      <li class="dropdown user user-menu">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
          <span class="hidden-xs capitalize"> {!! session('user')['user_firstname'] !!} </span>
        </a>
        <ul class="dropdown-menu">
          <!-- Menu Footer-->
          <li class="user-footer">
            <ul class="nav">
              <li class="treeview"><a href="{!! route('hw.logout') !!}">Sign Out</a></li>
            </ul>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>