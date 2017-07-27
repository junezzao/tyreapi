<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class UpdateMerchantPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $role = Role::where('name', '=', 'Client Admin')->first();
        $permissions = Permission::where('name', '=', 'Edit merchant')->orWhere('name', '=', 'View Merchant')->get();
        foreach ($permissions as $p) {
            $role->detachPermission($p);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $role = Role::where('name', '=', 'Client Admin')->first();
        $permissions = Permission::where('name', '=', 'Edit merchant')->orWhere('name', '=', 'View Merchant')->get();
        foreach ($permissions as $p) {
            $role->attachPermission($p);
        }
    }
}
