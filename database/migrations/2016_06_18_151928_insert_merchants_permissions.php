<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class InsertMerchantsPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create merchant',
                          'slug' => 'create.merchant',
                          'description' => '',
                       ],
                       [
                          'name' => 'View merchant',
                          'slug' => 'view.merchant',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit merchant',
                          'slug' => 'edit.merchant',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete merchant',
                          'slug' => 'delete.merchant',
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
            if ($p->name == 'Create merchant') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();
            } elseif ($p->name == 'Edit merchant' || $p->name == 'View merchant') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Account Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'Delete merchant') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();
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
