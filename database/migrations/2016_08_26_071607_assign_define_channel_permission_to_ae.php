<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignDefineChannelPermissionToAe extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permission = Permission::where('slug', '=', 'define.channel')->first();
        $role = Role::where('slug', '=', 'accountexec')->first();
        $role->attachPermission($permission);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permission = Permission::where('slug', '=', 'define.channel')->first();
        $role = Role::where('slug', '=', 'accountexec')->first();
        $role->detachPermission($permission); 
    }
}
