<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyOrderStatusLogTableForeignKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('order_status_log', function ($table) {
            $table->dropForeign('sale_status_log_sale_id_foreign');
            $table->dropIndex('sale_status_log_sale_id_foreign');
            $table->foreign('order_id')->references('id')->on('orders');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_status_log', function ($table) {
            $table->dropForeign('order_status_log_order_id_foreign');
            $table->dropIndex('order_status_log_order_id_foreign');
        });

        DB::statement('ALTER TABLE order_status_log ADD INDEX sale_status_log_sale_id_foreign (order_id)');
        DB::statement('ALTER TABLE order_status_log ADD CONSTRAINT sale_status_log_sale_id_foreign FOREIGN KEY (order_id) REFERENCES sales(sale_id)');
    }
}
