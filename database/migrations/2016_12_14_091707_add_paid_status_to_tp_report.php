<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPaidStatusToTpReport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report', function(Blueprint $table){
            $table->boolean('paid_status')->after('net_payout_currency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_report', function(Blueprint $table){
            $table->dropColumn('paid_status');
        });
    }
}
