<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateAndAssignFailedOrdersPermission extends Migration
{
    protected $permissions = array(
        [
            'name' => 'View Failed Orders',
            'slug' => 'view.failedorders',
            'description' => 'View failed orders.',
        ],
    );

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Account Executive', 'Operations Executive', 'Channel Manager', 'Merchant Admin', 'Merchant User', 'Warehouse Executive'])->get();

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
