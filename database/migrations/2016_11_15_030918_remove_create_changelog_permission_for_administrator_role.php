<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class RemoveCreateChangelogPermissionForAdministratorRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permission = Permission::where('slug', '=', 'create.changelog')->first();
        $role = Role::where('slug', '=', 'administrator')->first();

        $role->detachPermission($permission);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permission = Permission::where('slug', '=', 'create.changelog')->first();
        $role = Role::where('slug', '=', 'administrator')->first();

        $role->attachPermission($permission);
    }
}
