<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;

class CreateUserRoleWarehouseExecutive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Role::create([
            'name' => 'Warehouse Executive',
            'slug' => 'warehousexec',
            'description' => 'Warehouse Executive',
            'level' => 4
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Role::where('name', 'Warehouse Executive')->delete();
    }
}
