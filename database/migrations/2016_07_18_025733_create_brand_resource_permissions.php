<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateBrandResourcePermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create brand',
                          'slug' => 'create.brand',
                          'description' => '',
                       ],
                       [
                          'name' => 'View brand',
                          'slug' => 'view.brand',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit brand',
                          'slug' => 'edit.brand',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete brand',
                          'slug' => 'delete.brand',
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
            if ($p->name == 'Create brand' || $p->name == 'Edit brand'  || $p->name == 'Delete brand') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'View brand') {
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
