<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class InsertAndAssignPermissionsForChannels extends Migration
{
    protected $permissions = array([
                          'name' => 'Create channel',
                          'slug' => 'create.channel',
                          'description' => '',
                       ],
                       [
                          'name' => 'View channel',
                          'slug' => 'view.channel',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit channel',
                          'slug' => 'edit.channel',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete channel',
                          'slug' => 'delete.channel',
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
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();

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
