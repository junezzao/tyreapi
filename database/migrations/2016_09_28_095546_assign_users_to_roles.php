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
        // get all hubwire users
        $hwUsers = User::where('email', 'like', '%@hubwire.com')->get();
        $superadmin = ['hehui@hubwire.com', 'rachel@hubwire.com', 'yuki@hubwire.com', 'jun@hubwire.com', 'mark@hubwire.com'];
        $admin = ['annnee@hubwire.com', 'chris@hubwire.com', 'cyndel@huwbire.com', 'mahadhir@hubwire.com', 
                  'gary@hubwire.com', 'alex@huwbire.com', 'gary@hubwire.com', 'geraldine@hubwire.com',
                  'saerah@hubwire.com', 'tianna@hubwire.com'];
        $finance = ['angie@hubwire.com', 'kim@hubwire.com', 'nanthini@hubwire.com', 'martijn@hubwire.com'];
        $oe = ['prashan@hubwire.com', 'viknes@hubwire.com', 'jedidiah@hubwire.com', 'jagedes@hubwire.com'];

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
