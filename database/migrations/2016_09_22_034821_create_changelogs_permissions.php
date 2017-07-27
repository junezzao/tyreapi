<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateChangelogsPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create changelog',
                          'slug' => 'create.changelog',
                          'description' => '',
                       ],
                       [
                          'name' => 'View changelog',
                          'slug' => 'view.changelog',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit changelog',
                          'slug' => 'edit.changelog',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete changelog',
                          'slug' => 'delete.changelog',
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
            if ($p->name == 'Create changelog' || $p->name == 'Edit changelog'  || $p->name == 'Delete changelog') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator'])->get();
            } elseif ($p->name == 'View changelog') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Finance', 'Channel Manager', 'Client Admin', 'Client User'])->get();
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
