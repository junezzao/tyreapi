<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateSuppliersPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create supplier',
                          'slug' => 'create.supplier',
                          'description' => '',
                       ],
                       [
                          'name' => 'View supplier',
                          'slug' => 'view.supplier',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit supplier',
                          'slug' => 'edit.supplier',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete supplier',
                          'slug' => 'delete.supplier',
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
            if ($p->name == 'Create supplier' || $p->name == 'Delete supplier') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'View supplier' || $p->name == 'Edit supplier') {
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
