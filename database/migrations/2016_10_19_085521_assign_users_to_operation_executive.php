<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;

class AssignUsersToOperationExecutive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = User::whereIn('email', [
            'waiwai@hubwire.com', 
            'prashan@hubwire.com',  
            'viknes@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Operations Executive')->first();

        foreach ($users as $user) {
            $user->detachAllRoles();
            $user->attachRole($role);
            $user->category = 'Operations Executive';
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
        $users = User::whereIn('email', [
            'waiwai@hubwire.com', 
            'prashan@hubwire.com',  
            'viknes@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Operations Executive')->first();

        foreach ($users as $user) {
            $user->detachRole($role);
            $user->category = NULL;
            $user->save();
        }
    }
}
