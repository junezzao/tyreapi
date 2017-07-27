<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoneyFlowInChannelDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('channel_details', function (Blueprint $table) {
            $table->string('money_flow', 10)->nullable()->default('FMHW')->after('webhook_signature');
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
            $table->dropColumn(['money_flow']);
        });
    }
}
