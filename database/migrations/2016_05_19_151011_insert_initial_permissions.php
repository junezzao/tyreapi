<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class InsertInitialPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = array([
                          'name' => 'Create user',
                          'slug' => 'create.user',
                          'description' => '',
                       ],
                       [
                          'name' => 'View user',
                          'slug' => 'view.user',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit user',
                          'slug' => 'edit.user',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete user',
                          'slug' => 'delete.user',
                          'description' => '',
                       ]);
        
        foreach ($permissions as $permission) {
            $p = Permission::create($permission);
            if ($p->name == 'Create user' || $p->name == 'View user') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Account Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'Edit user' || $p->name == 'Delete user') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Client Admin'])->get();
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
        Permission::whereIn('name', array_fetch($this->permissions, 'name'))->delete();
    }
}
