<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class AssignPermissionsToChannelManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = Permission::whereIn('slug', [
            'view.user', 
            'view.return', 
            'view.channel',
            'edit.channel', 
            'view.merchant', 
            'view.restock',
            'view.brand',
            'view.product',
            'view.stocktransfer',
            'view.supplier'
        ])->get();
        $channelManager = Role::where('name', 'Channel Manager')->first();

        foreach ($permissions as $permission) {
            $channelManager->attachPermission($permission->id);
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
            'view.user', 
            'view.return', 
            'view.channel',
            'edit.channel', 
            'view.merchant', 
            'view.restock',
            'view.brand',
            'view.product',
            'view.stocktransfer',
            'view.supplier'
        ])->get();
        $channelManager = Role::where('name', 'Channel Manager')->first();

        foreach ($permissions as $permission) {
            $channelManager->detachPermission($permission->id);
        }
    }
}
