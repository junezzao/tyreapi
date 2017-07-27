<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class InsertAndAssignPermissionsForReturnLog extends Migration
{
    protected $permissions = array([
                          'name' => 'Create return',
                          'slug' => 'create.return',
                          'description' => '',
                       ],
                       [
                          'name' => 'View return',
                          'slug' => 'view.return',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit return',
                          'slug' => 'edit.return',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete return',
                          'slug' => 'delete.return',
                          'description' => '',
                       ]);

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Partners'])->get();

            foreach ($roles as $role) {
                $role->attachPermission($p);
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
        Permission::whereIn('name', array_pluck($this->permissions, 'name'))->delete();
    }
}
