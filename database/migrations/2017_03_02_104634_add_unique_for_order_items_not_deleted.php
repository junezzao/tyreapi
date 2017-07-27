<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUniqueForOrderItemsNotDeleted extends Migration
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
            $table->unique(['order_item_id', 'deleted_at']);
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
            $table->dropForeign('third_party_report_order_item_id_foreign');
        });
        Schema::table('third_party_report',function(Blueprint $table)
        {
            $table->dropUnique('third_party_report_order_item_id_deleted_at_unique');
        });
    }
}
