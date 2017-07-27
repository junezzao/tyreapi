<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateChannelSkuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_sku', function ($table) {
            $table->dropForeign('channel_sku_client_id_foreign');
            $table->integer('merchant_id')->unsigned()->after('sku_id')->default(0);
        });
        DB::statement("ALTER TABLE channel_sku MODIFY COLUMN client_id INT(10) NOT NULL DEFAULT '0';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE channel_sku MODIFY COLUMN client_id INT(10) UNSIGNED NOT NULL;");
        Schema::table('channel_sku', function ($table) {
            $table->dropColumn('merchant_id');
            $table->foreign('client_id')->references('client_id')->on('clients');
        });
    }
}
