<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreatePermissionViewFinanceReport extends Migration
{
    protected $permissions = array([
                          'name' => 'View Finance Reports',
                          'slug' => 'view.financereport',
                          'description' => 'View the tab',
                       ],
                       [
                          'name' => 'View Generate Reports',
                          'slug' => 'view.generatereport',
                          'description' => 'View the sub-tab',
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
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Account Executive', 'Operations Executive', 'Merchant Admin'])->get();

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
