<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddControllerColumnToChannelTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_types', function ($table) {
            $table->string('controller')->nullable()->after('fields');
        });

        DB::table('channel_types')->where('name', 'Shopify')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ShopifyController']);
        DB::table('channel_types')->where('name', 'Lelong')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\LelongController']);
        DB::table('channel_types')->where('name', 'Lazada')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\LazadaController']);
        DB::table('channel_types')->where('name', 'Zalora')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ZaloraController']);
        DB::table('channel_types')->where('name', '11Street')->update(['controller' => 'App\Modules\ThirdParty\Http\Controllers\ElevenStreetController']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_types', function ($table) {
            $table->dropColumn('controller');
        });
    }
}
