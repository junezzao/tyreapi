<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AddEditAndDeletePermissionForUsersToOperationAndAccountExecutive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = Permission::whereIn('slug', ['delete.user', 'edit.user'])->get();
        $roles = Role::whereIn('slug', ['accountexec', 'operationsexec'])->get();

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                 $role->attachPermission($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permissions = Permission::whereIn('slug', ['delete.user', 'edit.user'])->get();
        $roles = Role::whereIn('slug', ['accountexec', 'operationsexec'])->get();

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                 $role->detachPermission($permission);
            }
        }
    }
}
