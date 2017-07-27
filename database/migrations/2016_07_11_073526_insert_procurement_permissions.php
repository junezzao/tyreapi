<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class InsertProcurementPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create restock',
                          'slug' => 'create.restock',
                          'description' => '',
                       ],
                       [
                          'name' => 'View restock',
                          'slug' => 'view.restock',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit restock',
                          'slug' => 'edit.restock',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete restock',
                          'slug' => 'delete.restock',
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
            if ($p->name == 'Create restock') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive'])->get();
            } elseif ($p->name == 'Edit restock' || $p->name == 'View restock') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive'])->get();
            } elseif ($p->name == 'Delete restock') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive'])->get();
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
