<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExtraFieldsForShopifyChannelType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('channel_types')->where('name', 'Shopify')->where('id', 6)
            ->update(array('fields' => '[{"label":"store_type","required":"1"},{"label":"refund_applicable","required":"1"}]'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('channel_types')->where('name', 'Shopify')->where('id', 6)
            ->update(array('fields' => null));
    }
}
