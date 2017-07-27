<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateInventoryPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create product',
                          'slug' => 'create.product',
                          'description' => '',
                       ],
                       [
                          'name' => 'View product',
                          'slug' => 'view.product',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit product',
                          'slug' => 'edit.product',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete product',
                          'slug' => 'delete.product',
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
            if ($p->name == 'Create product' || $p->name == 'Delete product') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'View product' || $p->name == 'Edit product') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin', 'Client User'])->get();
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
