<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateQuantityLogAppTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quantity_log_app', function (Blueprint $table) {
            $table->unsignedInteger('channel_sku_id')->change();
            $table->foreign('channel_sku_id')->references('channel_sku_id')->on('channel_sku');
            $table->unsignedInteger('ref_table_id')->change();
            $table->string('ref_table' ,30)->change();
            $table->text('remarks')->before('created_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quantity_log_app', function (Blueprint $table) {
            $table->dropForeign('channel_sku_channel_sku_id_foreign');
            $table->integer('channel_sku_id')->change();
            $table->integer('ref_table_id')->change();
            $table->string('ref_table' ,20)->change();
            $table->text('remarks')->after('triggered_at')->change();
        });
    }
}
