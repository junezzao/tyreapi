<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExternalUrlColumnToProductMediaThirdPartyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_media_third_party', function(Blueprint $table)
        {
            $table->string('external_url')->after('third_party_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_media_third_party', function(Blueprint $table)
        {
            $table->dropColumn('external_url');
        });
    }
}
