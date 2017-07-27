<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveTpReportUq extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report',function(Blueprint $table)
        {
            $table->dropForeign('third_party_report_order_item_id_foreign');
        });
        Schema::table('third_party_report',function(Blueprint $table)
        {
            $table->dropUnique('third_party_report_order_item_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_report',function(Blueprint $table)
        {
             $table->integer('order_item_id')->unique()->change()->unsigned();
        });
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
        });
    }
}
