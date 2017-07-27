<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveMemberIdForeignKeyOnOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        // orders
        Schema::table('orders', function ($table) {
            $table->dropForeign('orders_member_id_foreign');
        });
        Schema::enableForeignKeyConstraints();
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
