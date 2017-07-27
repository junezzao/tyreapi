<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddChannelDetailsSaleAmount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_details', function (Blueprint $table) {
            $table->boolean('sale_amount', 10)->nullable()->default(1)->after('money_flow');
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
            $table->dropColumn(['sale_amount']);
        });
    }
}
