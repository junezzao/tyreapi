<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\DeliveryOrder;

class UpdateAdminIdToUserId extends Migration
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

        DB::transaction(function () use ($client_admin, $hw_admin) {
          
            // delivery_orders admin_id
            Schema::table('delivery_orders', function($table) {
                $table->integer('user_id')->after('admin_id');
            });

            DeliveryOrder::withTrashed()->chunk(1000, function($delivery_orders) use ($client_admin, $hw_admin){
                foreach ($delivery_orders as $do) {
                    $do->person_incharge = $client_admin[$do->person_incharge];
                    $do->user_id = $client_admin[$do->admin_id];
                    $do->save();
                }
            });
            Schema::table('delivery_orders', function($table) {
                $table->dropColumn('admin_id');
            });

            // fulfillment_orders admin_id
            Schema::table('fulfillment_orders', function($table) {
                $table->integer('user_id')->after('admin_id');
            });

            DB::table('fulfillment_orders')->chunk(1000, function($fulfillment_orders) use ($client_admin, $hw_admin){
                foreach ($fulfillment_orders as $fo) {
                    DB::table('fulfillment_orders')->where('fulfill_id', $fo->fulfill_id)->update(['user_id' => $client_admin[$fo->admin_id]]);
                }
            });
            Schema::table('fulfillment_orders', function($table) {
                $table->dropColumn('admin_id');
            });

            // order_notes. user_id column renamed but data is reading from client_admin
            Schema::table('order_notes', function($table) {
                $table->integer('user_id2')->after('user_id');
            });

            DB::table('order_notes')->chunk(1000, function($order_notes) use ($client_admin, $hw_admin){
                foreach ($order_notes as $note) {
                    if($note->user_id != 0)
                        DB::table('order_notes')->where('id', $note->id)->update(['user_id2' => ($note->user_id != 0 ? $client_admin[$note->user_id] : 0)]);
                }
            });
            Schema::table('order_notes', function($table) {
                $table->dropColumn('user_id');
                $table->renameColumn('user_id2', 'user_id');
            });

            // order_status_log. user_id column renamed but data is reading from client_admin
            Schema::table('order_status_log', function($table) {
                $table->integer('user_id2')->after('user_id');
            });

            DB::table('order_status_log')->chunk(1000, function($order_status_log) use ($client_admin, $hw_admin){
                foreach ($order_status_log as $log) {
                    DB::table('order_status_log')->where('id', $log->id)->update(['user_id2' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                }
            });
            Schema::table('order_status_log', function($table) {
                $table->dropColumn('user_id');
                $table->renameColumn('user_id2', 'user_id');
            });

            // purchase_batches admin_id
            Schema::table('purchase_batches', function($table) {
                $table->integer('user_id')->after('admin_id');
            });

            DB::table('purchase_batches')->chunk(1000, function($purchase_batches) use ($client_admin, $hw_admin){
                foreach ($purchase_batches as $batch) {
                    DB::table('purchase_batches')->where('batch_id', $batch->batch_id)->update(['user_id' => ($batch->admin_id != 0 ? $client_admin[$batch->admin_id] : 0)]);
                }
            });
            Schema::table('purchase_batches', function($table) {
                $table->dropColumn('admin_id');
            });

            // reject_log client_admin, hw_admin. drop client_admin column, maintain hw_admin column
            Schema::table('reject_log', function($table) {
                $table->integer('user_id')->after('client_admin');
            });

            DB::table('reject_log')->chunk(1000, function($reject_log) use ($client_admin, $hw_admin){
                foreach ($reject_log as $log) {
                    DB::table('reject_log')->where('id', $log->id)->update(['user_id' => ($log->client_admin != 0 ? $client_admin[$log->client_admin] : 0)]);
                }
            });
            Schema::table('reject_log', function($table) {
                $table->dropColumn('client_admin');
            });

            // stock_transfer admin_id
            Schema::table('stock_transfer', function($table) {
                $table->renameColumn('admin_id', 'user_id');
            });

            // return_log admin_id
            Schema::table('return_log', function($table) {
                $table->integer('user_id')->after('admin_id');
            });

            DB::table('return_log')->chunk(1000, function($return_log) use ($client_admin, $hw_admin){
                foreach ($return_log as $log) {
                    DB::table('return_log')->where('id', $log->id)->update(['user_id' => ($log->admin_id != 0 ? $client_admin[$log->admin_id] : 0)]);
                }
            });
            Schema::table('return_log', function($table) {
                $table->dropColumn('admin_id');
            });

            // store_credits_log admin_id
            Schema::table('store_credits_log', function($table) {
                $table->integer('user_id')->after('admin_id');
            });

            DB::table('store_credits_log')->chunk(1000, function($store_credits_log) use ($client_admin, $hw_admin){
                foreach ($store_credits_log as $log) {
                    DB::table('store_credits_log')->where('log_id', $log->log_id)->update(['user_id' => ($log->admin_id != 0 ? $client_admin[$log->admin_id] : 0)]);
                }
            });
            Schema::table('store_credits_log', function($table) {
                $table->dropColumn('admin_id');
            });

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
                $hw_admin[$user->id] = $old['hw_admins'];
            }
            if(isset($old['client_admins'])) {
                $client_admin[$user->id] = $old['client_admins'];
            }
        }

        DB::transaction(function () use ($client_admin, $hw_admin) {
        
            // delivery_orders 
            Schema::table('delivery_orders', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DeliveryOrder::withTrashed()->chunk(1000, function($delivery_orders) use ($client_admin, $hw_admin){
                foreach ($delivery_orders as $do) {
                    $do->person_incharge = $client_admin[$do->person_incharge];
                    $do->admin_id = $client_admin[$do->user_id];
                    $do->save();
                }
            });
            Schema::table('delivery_orders', function($table) {
                $table->dropColumn('user_id');
            });

            // fulfillment_orders 
            Schema::table('fulfillment_orders', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('fulfillment_orders')->chunk(1000, function($fulfillment_orders) use ($client_admin, $hw_admin){
                foreach ($fulfillment_orders as $fo) {
                    DB::table('fulfillment_orders')->where('fulfill_id', $fo->fulfill_id)->update(['admin_id' => $client_admin[$fo->user_id]]);
                }
            });
            Schema::table('fulfillment_orders', function($table) {
                $table->dropColumn('user_id');
            });

            // order_notes
            Schema::table('order_notes', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('order_notes')->chunk(1000, function($order_notes) use ($client_admin, $hw_admin){
                foreach ($order_notes as $note) {
                    DB::table('order_notes')->where('id', $note->id)->update(['admin_id' => ($note->user_id != 0 ? $client_admin[$note->user_id] : 0)]);
                }
            });
            Schema::table('order_notes', function($table) {
                $table->dropColumn('user_id');
                $table->renameColumn('admin_id', 'user_id');
            });

            // order_status_log
            Schema::table('order_status_log', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('order_status_log')->chunk(1000, function($order_status_log) use ($client_admin, $hw_admin){
                foreach ($order_status_log as $log) {
                    DB::table('order_status_log')->where('id', $log->id)->update(['admin_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                }
            });
            Schema::table('order_status_log', function($table) {
                $table->dropColumn('user_id');
                $table->renameColumn('admin_id', 'user_id');
            });

            // purchase_batches
            Schema::table('purchase_batches', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('purchase_batches')->chunk(1000, function($purchase_batches) use ($client_admin, $hw_admin){
                foreach ($purchase_batches as $batch) {
                    DB::table('purchase_batches')->where('batch_id', $batch->batch_id)->update(['admin_id' => $client_admin[$batch->user_id]]);
                }
            });
            Schema::table('purchase_batches', function($table) {
                $table->dropColumn('user_id');
            });

            // return_log
            Schema::table('return_log', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('return_log')->chunk(1000, function($return_log) use ($client_admin, $hw_admin){
                foreach ($return_log as $log) {
                    DB::table('return_log')->where('id', $log->id)->update(['admin_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                }
            });
            Schema::table('return_log', function($table) {
                $table->dropColumn('user_id');
            });

            // reject_log
            Schema::table('reject_log', function($table) {
                $table->integer('client_admin')->after('user_id');
            });

            DB::table('reject_log')->chunk(1000, function($reject_log) use ($client_admin, $hw_admin){
                foreach ($reject_log as $log) {
                    DB::table('reject_log')->where('id', $log->id)->update(['client_admin' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                }
            });
            Schema::table('reject_log', function($table) {
                $table->dropColumn('user_id');
            });

            // stock_transfer
            Schema::table('stock_transfer', function($table) {
                $table->renameColumn('user_id', 'admin_id');
            });

            // store_credits_log
            Schema::table('store_credits_log', function($table) {
                $table->integer('admin_id')->after('user_id');
            });

            DB::table('store_credits_log')->chunk(1000, function($store_credits_log) use ($client_admin, $hw_admin){
                foreach ($store_credits_log as $log) {
                    DB::table('store_credits_log')->where('log_id', $log->log_id)->update(['admin_id' => ($log->user_id != 0 ? $client_admin[$log->user_id] : 0)]);
                }
            });
            Schema::table('store_credits_log', function($table) {
                $table->dropColumn('user_id');
            });
        
        });
    }
}
