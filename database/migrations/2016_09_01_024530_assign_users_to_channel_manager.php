<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;

class AssignUsersToChannelManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = User::whereIn('email', [
            'lynn@hubwire.com', 
            'denise@hubwire.com', 
            'waiwai@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Channel Manager')->first();

        foreach ($users as $user) {
            $user->detachAllRoles();
            $user->attachRole($role);
            $user->category = 'Channel Manager';
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
            'lynn@hubwire.com', 
            'denise@hubwire.com', 
            'waiwai@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Channel Manager')->first();

        foreach ($users as $user) {
            $user->detachRole($role);
            $user->category = NULL;
            $user->save();
        }
    }
}
