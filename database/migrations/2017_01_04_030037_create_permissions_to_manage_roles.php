<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreatePermissionsToManageRoles extends Migration
{
    protected $permissions = array([
                          'name' => 'Create roles',
                          'slug' => 'create.roles',
                          'description' => '',
                       ],
                       [
                          'name' => 'View roles',
                          'slug' => 'view.roles',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit roles',
                          'slug' => 'edit.roles',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete roles',
                          'slug' => 'delete.roles',
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

            if ($p->name == 'Create roles' || $p->name == 'Delete roles') {
                $roles = Role::whereIn('name', ['Super Administrator'])->get();
            } elseif ($p->name == 'View roles' || $p->name == 'Edit roles') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator'])->get();
            }

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
