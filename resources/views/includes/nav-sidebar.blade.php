<!-- sidebar: style can be found in sidebar.less -->
<section class="sidebar">
    @if(strcasecmp(session('user')['status'], 'Unverified') != 0)
    <ul class="sidebar-menu">
        <li class="header"></li>
        <li class="treeview">
            <a href="{{ route('hw.dashboard') }}">
                <i class="fa fa-home fa-lg"></i> <span>Dashboard</span> 
            </a>
        </li>

        <li class="header">ADMIN NAVIGATION</li>
        <li class="treeview">
            <a href="{{ route('1.0.hw.changelog.index') }}">
                <i class="fa fa-history"></i> <span>Changelog</span>
            </a>
        </li>
    </ul>
    @endif
</section>
<!-- /.sidebar -->