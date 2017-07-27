<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;
use Carbon\Carbon;

class AssignUsersToWarehouseExecutive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // insert test accounts
        $weId = DB::table('users')->insertGetId([
             'first_name' => 'Warehouse Executive',
             'last_name' => 'Test',
             'email' => 'warehouse_executive@hubwire.com',
             'password' => bcrypt('Hubwire!'),
             'remember_token' => '',
             'contact_no' => '0123456789',
             'address' => '',
             'timezone' => 'Asia/Kuala_Lumpur',
             'currency' => 'MYR',
             'status' => 'Active',
             'old_id' => '',
             'category' => null,
             'created_at' => Carbon::now('UTC'),
             'updated_at' => Carbon::now('UTC'),
             'deleted_at' => null,
        ]);

        $users = User::whereIn('email', [
            'jagedes@hubwire.com', 
            'jedidiah@hubwire.com', 
            'surendra@hubwire.com', 
            'padhma@hubwire.com', 
            'hazlin@hubwire.com',
            'warehouse_executive@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Warehouse Executive')->first();

        foreach ($users as $user) {
            $user->detachAllRoles();
            $user->attachRole($role);
            $user->category = 'Warehouse Executive';
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
            'jagedes@hubwire.com', 
            'jedidiah@hubwire.com', 
            'surendra@hubwire.com', 
            'padhma@hubwire.com', 
            'hazlin@hubwire.com',
            'warehouseexec@hubwire.com',
            'warehouse_executive@hubwire.com'
        ])->get();

        $role = Role::where('name', 'Warehouse Executive')->first();

        foreach ($users as $user) {
            $user->detachRole($role);
            $user->category = NULL;
            $user->save();
        }

        $we = User::where('email', 'warehouse_executive@hubwire.com')->first();
        User::destroy($we->id);
    }
}
