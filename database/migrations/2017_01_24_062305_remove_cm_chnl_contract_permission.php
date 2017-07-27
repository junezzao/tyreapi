<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class RemoveCmChnlContractPermission extends Migration
{

    protected $permissions = array([
                          'name' => 'Create channel contract',
                          'slug' => 'create.channelcontract',
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
            $p = Permission::where('slug', '=', $permission['slug'])->get();
                $roles = Role::whereIn('name', ['Finance', 'Channel Manager'])->get();

            foreach ($roles as $role) {
                $role->detachPermission($p);
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
        foreach ($this->permissions as $permission) {
            $p = Permission::where('slug', '=', $permission['slug'])->get();

                $roles = Role::whereIn('name', ['Finance', 'Channel Manager'])->get();

            foreach ($roles as $role) {
                $role->attachPermission($p);
            }
        }
    }
}
