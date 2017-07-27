<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class InsertMergeClientAdminHwAdminTableData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // grab all data from hw_admins table and move to new users table
        //$hw_admins = DB::connection('old_db')->table('hw_admins')->get();
        $hw_admins = DB::table('hw_admins')->get();

        // grab all data from client_admins table and move to new users table
        //$client_admins = DB::connection('old_db')->table('client_admins')->get();
        $client_admins = DB::table('client_admins')->get();

        // insert hw_admins rows into new users table
        foreach ($hw_admins as $user) {
            //DB::connection('testing')->table('users')->insert(
            DB::table('users')->insert([
                'name' => ucwords($user->admin_name),
                 'email' => $user->admin_email,
                 'password' => $user->admin_password,
                 'remember_token' => '',
                 'contact' => '',
                 'address' => '',
                 'timezone' => 'Asia/Kuala_Lumpur',
                 'currency' => 'MYR',
                 'status' => (empty($user->deleted_at) ? 'Active' : 'Deleted'),
                 //'old_id' => $user->admin_id,
                 'old_id' => json_encode(['hw_admins' => $user->admin_id]),
                 'category' => 'Administrator',
                 'created_at' => $user->created_at,
                 'updated_at' => Carbon::now('UTC'),
                 'deleted_at' => $user->deleted_at,
            ]);
        }
        // insert client_admins rows into new users table
        foreach ($client_admins as $user) {
            $exist = false;
            foreach ($hw_admins as $admin) {
                if ($user->admin_email == $admin->admin_email) {
                    $exist = true;
                    $c = DB::table('users')->select('id', 'old_id')->where('email', '=', $user->admin_email)->first();
                    $old_id = json_decode($c->old_id, true);
                    $old_id = array_add($old_id, 'client_admins', $user->admin_id);
                    DB::table('users')->where('id', $c->id)->update(['old_id' => json_encode($old_id)]);
                }
            }
            if (false === $exist) {
                //DB::connection('mysql2')->table('users')->insert(
                DB::table('users')->insert([
                     'name' => ucwords($user->admin_name),
                     'email' => $user->admin_email,
                     'password' => $user->admin_password,
                     'remember_token' => '',
                     'contact' => '',
                     'address' => '',
                     'timezone' => 'Asia/Kuala_Lumpur',
                     'currency' => 'MYR',
                     'status' => (empty($user->deleted_at) ? 'Active' : 'Deleted'),
                     'old_id' => json_encode(['client_admins' => $user->admin_id]),
                     'category' => 'Client Admin',
                     'created_at' => $user->created_at,
                     'updated_at' => Carbon::now('UTC'),
                     'deleted_at' => $user->deleted_at,
                ]);
            }
        }

        // insert test accounts
        $testUsers = array('super administrator', 'administrator', 'finance', 'account executive','client admin', 'client user');
        foreach ($testUsers as $user) {
            DB::table('users')->insert([
                 'name' => ucwords($user),
                 'email' => str_replace(' ', '_', str_replace('administrator', 'admin', $user)).'@hubwire.com',
                 'password' => bcrypt('Hubwire!'),
                 'remember_token' => '',
                 'contact' => '0123456789',
                 'address' => '',
                 'timezone' => 'Asia/Kuala_Lumpur',
                 'currency' => 'MYR',
                 'status' => 'Active',
                 'old_id' => '',
                 'category' => ucwords($user),
                 'created_at' => Carbon::now('UTC'),
                 'updated_at' => Carbon::now('UTC'),
                 'deleted_at' => null,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // truncate the user table
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        //DB::connection('testing')->table('users')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
