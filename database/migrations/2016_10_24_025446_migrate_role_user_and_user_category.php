<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;

class MigrateRoleUserAndUserCategory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $change_role_name = Role::where('slug', '=', 'clientadmin')->get();
        foreach ($change_role_name as $name) {

            $name->name = 'Merchant Admin';
            $name->save();
        }

        $change_role_name2 = Role::where('slug', '=', 'clientuser')->get();
        foreach ($change_role_name2 as $name) {

            $name->name = 'Merchant User';
            $name->save();
        }

        $users = User::all();
        
        foreach ($users as $user) {

            $user->detachAllRoles();
            $role = Role::where('name','=',$user->category)->get();
            $user->attachRole($role);
            $user->save();
            
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $change_role_name = Role::where('slug', '=', 'clientadmin')->get();
        foreach ($change_role_name as $name) {

            $name->name = 'Client Admin';
            $name->save();
        }

        $change_role_name2 = Role::where('slug', '=', 'clientuser')->get();
        foreach ($change_role_name2 as $name) {

            $name->name = 'Client User';
            $name->save();
        }

        $users = User::all();
        
        foreach ($users as $user) {

            $user->detachAllRoles();
            $role = Role::where('name','=',$user->category)->first();
            $user->attachRole($role);
            $user->save();
            
        }
    }
}
