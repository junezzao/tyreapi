<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyHwFeeColumnToStoreThreeDecimalPlaces extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('hw_fee', 8, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('hw_fee', 8, 2)->change();
        });
    }
}
