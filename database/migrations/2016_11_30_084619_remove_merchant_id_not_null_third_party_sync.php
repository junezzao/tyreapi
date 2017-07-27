<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveMerchantIdNotNullThirdPartySync extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_sync', function(Blueprint $table){
            $table->integer('merchant_id')->change()->nullable()->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('third_party_sync', function(Blueprint $table){
            $table->integer('merchant_id')->change()->nullable(false)->unsigned();
        });
    }
}
