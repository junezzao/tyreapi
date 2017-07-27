<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreatePermissionStockOutManifests extends Migration
{
    protected $permissions = array([
                          'name' => 'Cancel Stock Out Manifest',
                          'slug' => 'cancel.stockout.manifest',
                          'description' => 'Cancel Stock Out Manifest',
                       ],
                       [
                          'name' => 'Complete Stock Out Manifest',
                          'slug' => 'complete.stockout.manifest',
                          'description' => 'Complete Stock Out Manifest',
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
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive'])->get();

            foreach ($roles as $role) {
                $role->attachPermission($p->id);
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
            Permission::where('slug', $permission['slug'])->delete();
        }
    }
}
