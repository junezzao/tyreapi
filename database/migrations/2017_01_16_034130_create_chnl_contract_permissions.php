<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateChnlContractPermissions extends Migration
{

    protected $permissions = array([
                          'name' => 'Create channel contract',
                          'slug' => 'create.channelcontract',
                          'description' => '',
                       ],
                       [
                          'name' => 'View channel contract',
                          'slug' => 'view.channelcontract',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit channel contract',
                          'slug' => 'edit.channelcontract',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete channel contract',
                          'slug' => 'delete.channelcontract',
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
            
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Channel Manager'])->get();

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
