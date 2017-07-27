<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignAePermissionToOe extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $operarionsExecutive = Role::where('slug', 'operationsexec')->first();
        $accountExecutive = Role::where('slug', 'accountexec')->first();
        //get the permission detail by role id
        $aePermissions = Role::where('id', $accountExecutive->id)->with('permissions')->get();

        foreach ($aePermissions as $getPermissions) {
            $permissions = $getPermissions->permissions;
            
            foreach ($permissions as $permission) {
                $operarionsExecutive->attachPermission($permission->id);
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
        $operarionsExecutive = Role::where('slug', 'operationsexec')->first();
        $accountExecutive = Role::where('slug', 'accountexec')->first();
        //get the permission detail by role id
        $aePermissions = Role::where('id', $accountExecutive->id)->with('permissions')->get();

        foreach ($aePermissions as $getPermissions) {
            $permissions = $getPermissions->permissions;
            
            foreach ($permissions as $permission) {
                $operarionsExecutive->detachPermission($permission->id);
            }
        }
    }
}