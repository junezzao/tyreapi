<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrderHistoryUserId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = DB::table('users')->get();
        $hw_admin = array();
        $client_admin = array();
        foreach ($users as $user) {
            $old = json_decode($user->old_id, true);
            
            if (isset($old['hw_admins'])) {
                $hw_admin[$old['hw_admins']] = $user->id;
            }
            if(isset($old['client_admins'])) {
                $client_admin[$old['client_admins']] = $user->id;
            }
        }

        DB::table('order_history')->where('updated_at', null)->chunk(1000, function($logs) use ($client_admin, $hw_admin){
            
            foreach ($logs as $log) {
                if($log->ref_type == 'order_status_log')
                    DB::table('order_history')->where('id', $log->id)->update(['user_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                elseif ($log->ref_type == 'return_log')
                    DB::table('order_history')->where('id', $log->id)->update(['user_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                else
                    DB::table('order_history')->where('id', $log->id)->update(['user_id' => 0]);
            }

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $users = DB::table('users')->get();
        $hw_admin = array();
        $client_admin = array();
        foreach ($users as $user) {
            $old = json_decode($user->old_id, true);
            
            if (isset($old['hw_admins'])) {
                $hw_admin[$old['hw_admins']] = $user->id;
            }
            if(isset($old['client_admins'])) {
                $client_admin[$old['client_admins']] = $user->id;
            }
        }

        DB::table('order_history')->where('updated_at', null)->chunk(1000, function($logs) use ($client_admin, $hw_admin){
            
            foreach ($logs as $log) {
                if($log->ref_type == 'order_status_log')
                    DB::table('order_history')->where('id', $log->id)->update(['user_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                elseif ($log->ref_type == 'return_log')
                    DB::table('order_history')->where('id', $log->id)->update(['user_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
            }

        });
    }
}
