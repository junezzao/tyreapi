<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyControllerColumnValueInChannelTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('channel_types')->update(['controller' => 'NonIntegratedController']);

        DB::table('channel_types')->where('name', 'Shopify')->update(['controller' => 'ShopifyController']);
        DB::table('channel_types')->where('name', 'Lelong')->update(['controller' => 'LelongController']);
        DB::table('channel_types')->where('name', 'Lazada')->update(['controller' => 'LazadaController']);
        DB::table('channel_types')->where('name', 'Zalora')->update(['controller' => 'ZaloraController']);
        DB::table('channel_types')->where('name', '11Street')->update(['controller' => 'ElevenStreetController']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('channel_types')->update(['controller' => null]);

        DB::table('channel_types')->where('name', 'Shopify')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ShopifyController']);
        DB::table('channel_types')->where('name', 'Lelong')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\LelongController']);
        DB::table('channel_types')->where('name', 'Lazada')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\LazadaController']);
        DB::table('channel_types')->where('name', 'Zalora')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ZaloraController']);
        DB::table('channel_types')->where('name', '11Street')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ElevenStreetController']);
    }
}
