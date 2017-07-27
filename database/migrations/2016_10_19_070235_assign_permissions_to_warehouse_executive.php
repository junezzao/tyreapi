<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignPermissionsToWarehouseExecutive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = Permission::whereIn('slug', [
            'view.restock', 
            'create.restock', 
            'edit.restock', 
            'delete.restock', 
            'view.product', 
            'create.product', 
            'edit.product', 
            'delete.product',
            'view.stocktransfer',
            'create.stocktransfer',
            'edit.stocktransfer',
            'delete.stocktransfer',
            'view.merchant',
            'view.channel',
            'view.return',
            'create.return',
            'edit.return',
            'delete.return',
            'view.brand',
            'view.supplier',
            'edit.supplier',
            'view.channelproduct',
            'edit.channelproduct',
            'view.dashboardcharts',
            'view.issuingcompany',
            'view.changelog'
        ])->get();
        $warehouseExecutive = Role::where('name', 'Warehouse Executive')->first();

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
            'view.restock', 
            'create.restock', 
            'edit.restock', 
            'delete.restock', 
            'view.product', 
            'create.product', 
            'edit.product', 
            'delete.product',
            'view.stocktransfer',
            'create.stocktransfer',
            'edit.stocktransfer',
            'delete.stocktransfer',
            'view.merchant',
            'view.channel',
            'view.return',
            'create.return',
            'edit.return',
            'delete.return',
            'view.brand',
            'view.supplier',
            'edit.supplier',
            'view.channelproduct',
            'edit.channelproduct',
            'view.dashboardcharts',
            'view.issuingcompany',
            'view.changelog'
        ])->get();
        $warehouseExecutive = Role::where('name', 'Warehouse Executive')->first();

        foreach ($permissions as $permission) {
            $warehouseExecutive->detachPermission($permission->id);
        }
    }
}
