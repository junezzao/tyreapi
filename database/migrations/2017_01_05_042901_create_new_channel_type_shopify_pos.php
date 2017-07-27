<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\Channel;

class CreateNewChannelTypeShopifyPos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channels = Channel::where('channel_type_id', '=', 6)->with('channel_detail')->get();
        $getPosChannel = '';
        foreach ($channels as $channel) {
            $extra_info = json_decode($channel->channel_detail->extra_info, true);
            foreach ($extra_info as $key => $value) {
                if ($key == 'store_type' && $value == 'POS') {
                    $getPosChannel .= '-'.$channel['name'].'('.$channel['id'].') ';
                }
            }
        }
        Log::info('The channel need to change to new channel type: '.$getPosChannel);
        
        $getShopifyData = ChannelType::where('name', 'Shopify')->first();
        $getFields = array();
        if (is_string($getShopifyData->fields)) {
            $getFields = json_decode($getShopifyData->fields,true);
        }
        
        foreach ($getFields as $key => $value) {
            if ($value['api'] == 'store_type') {
                $newFields = array_diff_key($getFields, [$key => $value]);
                ChannelType::where('name', 'Shopify')->update(['fields' => json_encode(array_values($newFields))]);
            }
        }
        $getShopifyDataAgain = ChannelType::where('name', 'Shopify')->first();
        $channelType = new ChannelType;
        $channelType->name          = 'Shopify POS';
        $channelType->status        = $getShopifyDataAgain->status;
        $channelType->fields        = $getShopifyDataAgain->fields;
        $channelType->third_party   = $getShopifyDataAgain->third_party;
        $channelType->controller    = $getShopifyDataAgain->controller;
        $channelType->site          = $getShopifyDataAgain->site;
        $channelType->manual_order  = $getShopifyDataAgain->manual_order;
        $channelType->type          = $getShopifyDataAgain->type;
        $channelType->save();
        $ShopifyPOSID = ChannelType::where('name', 'Shopify POS')->first();
        Log::info('Shopify POS ID: '.$ShopifyPOSID->id);
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $ShopifyPOSID = ChannelType::where('name', 'Shopify POS')->first();
        $getShopifyID = ChannelType::where('name', 'Shopify')->first();
        Channel::where('channel_type_id', '=', $ShopifyPOSID->id)->update(['channel_type_id' => $getShopifyID->id]);
        ChannelType::where('name', '=', 'Shopify POS')->first()->forceDelete();
    }
}
