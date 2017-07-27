<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignViewRestockPermissionToAllRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permission = Permission::where('slug', '=', 'view.restock')->first();
        $roles = Role::whereNotIn('name', ['Super Administrator', 'Administrator', 'Account Executive'])->get();

        foreach ($roles as $role) {
            $role->attachPermission($permission->id);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permission = Permission::where('slug', '=', 'view.restock')->first();
        $roles = Role::whereNotIn('name', ['Super Administrator', 'Administrator', 'Account Executive'])->get();

        foreach ($roles as $role) {
            $role->detachPermission($permission->id);
        }
    }
}
