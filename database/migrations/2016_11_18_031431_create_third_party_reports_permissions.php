<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateThirdPartyReportsPermissions extends Migration
{

    protected $permissions = array([
                              'name' => 'Create Third Party Report',
                              'slug' => 'create.tpreport',
                              'description' => '',
                           ],
                           [
                              'name' => 'View Third Party Report',
                              'slug' => 'view.tpreport',
                              'description' => '',
                           ],
                           [
                              'name' => 'Edit Third Party Report',
                              'slug' => 'edit.tpreport',
                              'description' => '',
                           ]);

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);
            $roles = Role::whereIn('name', ['Super Administrator', 'Finance'])->get();
            
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
        //
        Permission::whereIn('name', array_pluck($this->permissions, 'name'))->delete();
    }
}
