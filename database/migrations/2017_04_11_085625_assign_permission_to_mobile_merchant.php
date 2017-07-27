<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignPermissionToMobileMerchant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = Permission::whereIn('slug', [
            'view.product', 
            'view.supplier', 
            'view.channelproduct', 
            'view.changelog', 
            'view.failedorders', 
        ])->get();
        $warehouseExecutive = Role::where('name', 'Mobile Merchant')->first();

        foreach ($permissions as $permission) {
            $warehouseExecutive->attachPermission($permission->id);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permissions = Permission::whereIn('slug', [ 
            'view.product', 
            'view.supplier', 
            'view.channelproduct', 
            'view.changelog', 
            'view.failedorders', 
        ])->get();
        $warehouseExecutive = Role::where('name', 'Mobile Merchant')->first();

        foreach ($permissions as $permission) {
            $warehouseExecutive->detachPermission($permission->id);
        }
    }
}
