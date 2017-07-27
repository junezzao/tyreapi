<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixChannelContractRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_contract_rules', function ($table) {
            $table->dropForeign('channel_contract_rules_contract_id_foreign');
            $table->foreign('contract_id')->references('id')->on('channel_contracts')->onDelete('cascade');
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
        Schema::table('channel_contract_rules', function ($table) {
            $table->dropForeign('channel_contract_rules_contract_id_foreign');
            // take note the below FK references the wrong table.
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
        });
    }
}
