<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRetriesToThirdPartySync extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_sync', function(Blueprint $table){
            $table->integer('retries')->unsigned()->after('status')->default(0);
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
            $table->dropColumn('retries');
        });
    }
}
