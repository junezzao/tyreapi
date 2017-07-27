<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMinGuaranteeToOrderItems extends Migration
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
            $table->decimal('min_guarantee', 10, 3)->after('misc_fee');
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
            $table->dropColumn('min_guarantee');
        });
    }
}
