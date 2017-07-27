<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateRefundLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('refund_log', 'return_log');

        Schema::table('return_log', function ($table) {
            $table->renameColumn('log_id', 'id');
            $table->renameColumn('sale_id', 'order_id');
            $table->renameColumn('sale_item_id', 'order_item_id');
            $table->renameColumn('return_qty', 'quantity');

            $table->dropForeign('refund_log_sale_id_foreign');
            $table->dropIndex('refund_log_sale_id_foreign');
            $table->index(['order_id', 'order_item_id']);
            $table->foreign('order_id')->references('id')->on('orders');
        });

        DB::statement('ALTER TABLE return_log MODIFY COLUMN order_item_id INT(10) UNSIGNED NOT NULL AFTER order_id');
        DB::statement('ALTER TABLE return_log MODIFY COLUMN quantity INT(11) NOT NULL AFTER order_item_id');
        DB::statement('ALTER TABLE return_log MODIFY COLUMN refund_type VARCHAR(50) NULL');
        DB::statement('ALTER TABLE return_log MODIFY COLUMN remark TEXT NULL');

        Schema::table('return_log', function ($table) {
            //$table->string('status')->after('amount');  Removed 12/08/2016 as column already exist in table causing migration failure
            //$table->dateTime('completed_at')->nullable()->after('ref_id');
        });

        DB::table('return_log')->update([
            'status' => 'Restocked',
            'completed_at' => DB::raw('updated_at')
        ]);
        DB::table('return_log')->where('refund_type', '=', '')->update(['refund_type' => null]);
        DB::table('return_log')->where('remark', '=', '')->update(['remark' => null]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('return_log', 'refund_log');

        Schema::table('refund_log', function ($table) {
            $table->renameColumn('id', 'log_id');
            $table->renameColumn('order_id', 'sale_id');
            $table->renameColumn('order_item_id', 'sale_item_id');
            $table->renameColumn('quantity', 'return_qty');

            // $table->dropColumn('status');
            // $table->dropColumn('completed_at');

            $table->dropForeign('return_log_order_id_foreign');
            $table->dropIndex('return_log_order_id_order_item_id_index');
            $table->foreign('sale_id')->references('sale_id')->on('sales');
        });

        DB::table('refund_log')->where('refund_type', '=', null)->update(['refund_type' => '']);
        DB::table('refund_log')->where('remark', '=', null)->update(['remark' => '']);

        DB::statement('ALTER TABLE refund_log MODIFY COLUMN sale_item_id INT(10) UNSIGNED NOT NULL AFTER ref_id');
        DB::statement('ALTER TABLE refund_log MODIFY COLUMN return_qty INT(11) NOT NULL AFTER updated_at');
        DB::statement('ALTER TABLE refund_log MODIFY COLUMN refund_type VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE refund_log MODIFY COLUMN remark TEXT NOT NULL');
    }
}
