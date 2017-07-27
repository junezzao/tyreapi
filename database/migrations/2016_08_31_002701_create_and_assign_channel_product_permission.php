<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateAndAssignChannelProductPermission extends Migration
{
    protected $permissions = array([
                          'name' => 'Create channel product',
                          'slug' => 'create.channelproduct',
                          'description' => '',
                       ],
                       [
                          'name' => 'View channel product',
                          'slug' => 'view.channelproduct',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit channel product',
                          'slug' => 'edit.channelproduct',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete channel product',
                          'slug' => 'delete.channelproduct',
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
            if ($p->name == 'Create channel product' || $p->name == 'Delete channel product') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin'])->get();
            } elseif ($p->name == 'View channel product' || $p->name == 'Edit channel product') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive', 'Client Admin', 'Client User', 'Channel Manager'])->get();
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
