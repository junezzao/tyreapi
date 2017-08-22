<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;

class AssignUsersToRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // get all prog group users
        $hwUsers = User::where('email', 'like', '%@prog.com.my')->get();
        $superadmin = ['superadmin@prog.com.my'];
        $admin = ['admin@prog.com.my'];
        $finance = ['finance@prog.com.my'];
        $oe = ['oe@prog.com.my'];

        //set superadmin
        foreach ($hwUsers as $user) {
            if(in_array($user->email, $superadmin)){
                $superadminRole = Role::where('name', 'Super Administrator')->first();
                $user->detachAllRoles();
                $user->attachRole($superadminRole);
                $user->category = 'Super Administrator';
                $user->save();
            }

            if(in_array($user->email, $admin)){
                $adminRole = Role::where('name', 'Administrator')->first();
                $user->detachAllRoles();
                $user->attachRole($adminRole);
                $user->category = 'Administrator';
                $user->save();
            }

            if(in_array($user->email, $finance)){
                $financeRole = Role::where('name', 'Finance')->first();
                $user->detachAllRoles();
                $user->attachRole($adminRole);
                $user->category = 'Finance';
                $user->save();
            }

            if(in_array($user->email, $oe)){
                $oeRole = Role::where('name', 'Operations Executive')->first();
                $user->detachAllRoles();
                $user->attachRole($adminRole);
                $user->category = 'Operations Executive';
                $user->save();
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
    }
}
