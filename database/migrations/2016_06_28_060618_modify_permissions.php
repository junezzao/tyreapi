<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class ModifyPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Define channel',
                          'slug' => 'define.channel',
                          'description' => 'Create and edit channel type',
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

            if ($p->name == 'Define channel') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();
            }

            foreach ($roles as $role) {
                $role->attachPermission($p);
            }
        }

        // assign existing permission to user role
        $create = Permission::where('name', '=', 'Create channel')->first();
        $view = Permission::where('name', '=', 'View channel')->first();
        $delete = Permission::where('name', '=', 'Delete channel')->first();
        $edit = Permission::where('name', '=', 'Edit channel')->first();

        $roles = Role::whereIn('name', ['Account Executive', 'Client Admin', 'Client User'])->get();
            
        foreach ($roles as $role) {
            if ($role->name != 'Client User') {
                $role->attachPermission($create);
                $role->attachPermission($edit);
                $role->attachPermission($delete);
            }
            $role->attachPermission($view);
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
