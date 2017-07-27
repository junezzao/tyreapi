<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeChannelTypeShopeeController extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       DB::statement("UPDATE channel_types SET controller = 'ShopeeController' WHERE name = 'Shopee'"); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("UPDATE channel_types SET controller = 'NonIntegratedController' WHERE name = 'Shopee'"); 
    }
}
