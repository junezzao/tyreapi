<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertShippingProviderForChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $shipping_provider_data = array(
            ['50','[Derigr] Fabspy Online Marketplace','Shopify','Pos Laju',''],
            ['51','MDreams','Shopify','Pos Laju',''],
            ['57','Polo Haus','Shopify','GDex',''],
            ['62','Femme','Shopify','Pos Laju',''],
            ['63','Labceuticals','Shopify','Pos Laju',''],
            ['66','Good Virtues Co.','Shopify','Pos Laju',''],
            ['67','Badlab','Shopify','Pos Laju',''],
            ['68','Karadium / Clematis','Shopify','Pos Laju',''],
            ['71','Hubwire eStore','Shopify','Pos Laju',''],
            ['74','Beutiq','Shopify','Pos Laju',''],
            ['35','[Hubwire] Lelong','Lelong','Pos Laju',''],
            ['37','[Hubwire] Lazada','Lazada','GDEX-DS','NinjaVan'],
            ['49','[Bata Malaysia] Lazada','Lazada','SkyNet - DS','Ta-Q-Bin'],
            ['69','[Paragon Vest] Lazada','Lazada','Seller-Poslaju','Ta-Q-Bin'],
            ['75','LBJR Lazada','Lazada','SkyNet - DS',''],
            ['22','[Derigr] Zalora','Zalora','Marketplace GDex','Marketplace TaQBin'],
            ['38','[Hubwire] Zalora','Zalora','Marketplace GDex','Marketplace TaQBin'],
            ['27','[Derigr] 11 Street','11Street','Skynet',''],
            ['34','[Hubwire] 11 street','11Street','Skynet',''],
            ['70','[Paragon Vest] 11Street','11Street','Skynet',''],
            ['39','[Hubwire] GemFive','Offline Store','GDex',''],
            ['40','[Hubwire] Shopee','Offline Store','Poslaju',''],
            ['54','[Bata Malaysia] Gemfive','Offline Store','GDex',''],
            
            );

        //Schema::create('shipping_provider', function (Blueprint $table) {
        //    $table->increments('id');
        //    $table->string('sp_name');
        //    $table->string('channel_id');
        //    $table->string('channel_type');
        //    $table->string('channel_name');
        //    $table->string('cod');
        //    
        //});

        $x=0;
        foreach ($shipping_provider_data as $data) {
            $x++;
            $shippingProvider[$data[0]]['sp_name'] = $data[3];
            $shippingProvider[$data[0]]['channel_id'] = $data[0];
            $shippingProvider[$data[0]]['channel_type'] = $data[2];
            $shippingProvider[$data[0]]['channel_name'] = $data[1];
            $shippingProvider[$data[0]]['cod'] = $data[4];
        }
        
        $channel_details = DB::table('channel_details')->get();
        foreach ($channel_details as $detail) {
            $detail_extra_info = json_decode($detail->extra_info, true);
            foreach ($shippingProvider as $channel_id => $data) {
                if($detail->channel_id==$channel_id){
                    $detail_extra_info['shipping_provider'] = $data['sp_name'];
                    $detail_extra_info['shipping_provider_cod'] = $data['cod'];
                    $updateToDB = DB::table('channel_details')->where('channel_id', '=', $channel_id)->update(['extra_info' => json_encode($detail_extra_info)]);
                }
            }
        }
        
        //$db = DB::table('shipping_provider')->insert($shippingProvider);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $channel_details = DB::table('channel_details')->get();
        foreach ($channel_details as $detail) {
            $detail_extra_info = json_decode($detail->extra_info, true);
            unset($detail_extra_info['shipping_provider']);
            unset($detail_extra_info['shipping_provider_cod']);
            unset($detail_extra_info['cod']);
            $updateToDB = DB::table('channel_details')->where('channel_id', '=', $detail->channel_id)->update(['extra_info' => json_encode($detail_extra_info)]);
        }

       //Schema::drop('shipping_provider');
    }
}
