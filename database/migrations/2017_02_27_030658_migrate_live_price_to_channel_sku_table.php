<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelSKU;

class MigrateLivePriceToChannelSkuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = ChannelSKU::chunk(1000, function($channel_skus){
            foreach($channel_skus as $channel_sku)
            {
                if( strtotime($channel_sku->promo_start_date) >= strtotime('now') && strtotime($channel_sku->promo_end_date) < strtotime('now') )
                    $channel_sku->channel_sku_live_price = ($channel_sku->channel_sku_promo_price>0)?$channel_sku->channel_sku_promo_price:$channel_sku->channel_sku_price;
                else
                    $channel_sku->channel_sku_live_price = $channel_sku->channel_sku_price;

                $channel_sku->save();
                // \Log::info('update live price');
            }
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
    }
}
