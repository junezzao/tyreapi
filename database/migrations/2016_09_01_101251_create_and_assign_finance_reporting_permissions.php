<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateAndAssignFinanceReportingPermissions extends Migration
{

    protected $permissions = array([
                  'name' => 'View Reports',
                  'slug' => 'view.reports',
                  'description' => 'View and download reports',
               ],
               [
                  'name' => 'Create Issuing Company',
                  'slug' => 'create.issuingcompany',
                  'description' => '',
               ],
               [
                  'name' => 'View Issuing Company',
                  'slug' => 'view.issuingcompany',
                  'description' => '',
               ],
               [
                  'name' => 'Edit Issuing Company',
                  'slug' => 'edit.issuingcompany',
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
            $p = Permission::create($permission);
            if ($p->name == 'View Reports' || $p->name == 'View Issuing Company') {
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance', 'Account Executive'])->get();
            }else{
                $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();
            }
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
        Permission::whereIn('name', array_pluck($this->permissions, 'name'))->delete();
    }
}
