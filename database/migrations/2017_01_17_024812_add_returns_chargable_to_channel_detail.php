<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReturnsChargableToChannelDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_details', function ($table) {
            $table->boolean('returns_chargable')->default(0)->after('api_secret');  
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
            $table->dropColumn('returns_chargable');  
        });
    }
}
