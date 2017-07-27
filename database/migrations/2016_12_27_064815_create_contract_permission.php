<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateContractPermission extends Migration
{
    
    protected $permissions = array([
                          'name' => 'Create contract',
                          'slug' => 'create.contract',
                          'description' => '',
                       ],
                       [
                          'name' => 'View contract',
                          'slug' => 'view.contract',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit contract',
                          'slug' => 'edit.contract',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete contract',
                          'slug' => 'delete.contract',
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
            
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator'])->get();

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
