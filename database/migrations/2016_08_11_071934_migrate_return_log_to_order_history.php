<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateReturnLogToOrderHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $returns = DB::table('return_log')
                    ->leftJoin('order_items', 'return_log.order_item_id', '=', 'order_items.id')
                    ->leftJoin('channel_sku', 'order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftJoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                    ->orderBy('return_log.id', 'asc')
                    ->select('return_log.id', 'return_log.order_item_id', 'return_log.order_id', 'sku.hubwire_sku', 'return_log.admin_id', 'return_log.created_at')
                    ->get();
        foreach ($returns as $return) {
            if ($return->order_item_id > 0) {
                DB::table('order_history')->insert([
                    'order_id'      =>  $return->order_id,
                    'description'   =>  trans('order-history.description_order_item_restock', ['hwSku' => $return->hubwire_sku]),
                    'event'         =>  'Returned Item: Restocked',
                    'ref_type'      =>  'return_log',
                    'ref_id'        =>  $return->id,
                    'user_id'       =>  $return->admin_id,
                    'created_at'    =>  $return->created_at,
                ]);
            }
        }
        // readd back foreign key contraint dropped in 2016_08_11_034053_migrate_order_status_log_to_order_history
        Schema::table('order_history', function ($table) {
            //$table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('order_history')->where('event', '=', 'Returned Item: Restocked')->delete();
        Schema::table('order_history', function ($table) {
            //$table->dropForeign('order_history_order_id_foreign');
        });
    }
}
