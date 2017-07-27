<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PurchaseTablesModifications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $replenishments = DB::table('purchase_items')->select(DB::raw('DISTINCT(batch_id)'), 'item_replenishment')->get();

        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->tinyInteger('replenishment')->after('batch_status');
            DB::statement("ALTER TABLE purchase_batches MODIFY COLUMN deleted_at timestamp NULL AFTER channel_id");
            DB::statement("ALTER TABLE purchase_batches MODIFY COLUMN updated_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER channel_id");
            DB::statement("ALTER TABLE purchase_batches MODIFY COLUMN created_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER channel_id");
            DB::statement("ALTER TABLE purchase_batches MODIFY COLUMN receive_date datetime NULL AFTER channel_id");
            DB::statement("ALTER TABLE purchase_batches MODIFY COLUMN batch_date date AFTER channel_id");
        });

        foreach ($replenishments as $replenishment) {
            DB::table('purchase_batches')->where('batch_id', $replenishment->batch_id)
            ->update(['replenishment' => $replenishment->item_replenishment]);
        }
        
        Schema::table('purchase_items', function (Blueprint $table) {
            // $table->dropColumn(['item_status','item_replenishment']);
            DB::statement("ALTER TABLE purchase_items MODIFY COLUMN deleted_at timestamp NULL AFTER item_retail_price");
            DB::statement("ALTER TABLE purchase_items MODIFY COLUMN updated_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER item_retail_price");
            DB::statement("ALTER TABLE purchase_items MODIFY COLUMN created_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER item_retail_price");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropColumn('replenishment');
        });
        Schema::table('purchase_items', function (Blueprint $table) {
            // $table->tinyInteger('item_status');
            // $table->tinyInteger('item_replenishment');
        });
    }
}
