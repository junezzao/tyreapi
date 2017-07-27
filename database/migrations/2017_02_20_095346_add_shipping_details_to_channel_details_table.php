<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShippingDetailsToChannelDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_details', function ($table) {
            $table->tinyInteger('shipping_default')->default(1)->after('webhook_signature');
            $table->text('shipping_rate')->after('shipping_default')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_details', function ($table) {
            $table->dropColumn('shipping_default');
            $table->dropColumn('shipping_rate');
        });
    }
}
