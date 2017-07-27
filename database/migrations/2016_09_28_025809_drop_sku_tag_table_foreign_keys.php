<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropSkuTagTableForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sku_tag', function ($table) {
            $table->dropForeign('sku_tag_sku_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sku_tag', function ($table) {
            $table->foreign('sku_id')->references('sku_id')->on('sku');
        });
    }
}
