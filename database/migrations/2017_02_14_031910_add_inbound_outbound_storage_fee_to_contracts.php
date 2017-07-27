<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInboundOutboundStorageFeeToContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contracts', function ($table) {
            $table->double('inbound_fee',8,3)->after('guarantee');
            $table->double('outbound_fee',8,3)->after('guarantee');
            $table->double('storage_fee',8,3)->after('guarantee');
        });    
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contracts', function ($table) {
            $table->dropColumn(['inbound_fee','outbound_fee','storage_fee']);
        });
    }
}
