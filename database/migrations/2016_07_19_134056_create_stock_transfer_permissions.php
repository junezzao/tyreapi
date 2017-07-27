<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateStockTransferPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Create stock transfer',
                          'slug' => 'create.stocktransfer',
                          'description' => '',
                       ],
                       [
                          'name' => 'View stock transfer',
                          'slug' => 'view.stocktransfer',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit stock transfer',
                          'slug' => 'edit.stocktransfer',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete stock transfer',
                          'slug' => 'delete.stocktransfer',
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
            
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Account Executive', 'Operations Executive'])->get();
            
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
