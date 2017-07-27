<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ProductMedia;
use Es;
use DB;
use Log;

class MigrateOldChnlSkuCF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:migrateOldChnlSkuCF';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates custom field\'s channel SKU ID to it\'s new channel SKU ID';

    protected $cfIndex;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->cfIndex = 'channel_sku';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Running... elasticsearch:migrateOldChnlSkuCF');

        $data = DB::table('sku_mapping')->whereNotNull('new_sku_id')->whereNull('completed_at')->get();

        foreach ($data as $d) {
            $sku = SKU::where('sku_id', $d->sku_id)->firstOrFail();
            $newSku = SKU::where('sku_id', $d->new_sku_id)->firstOrFail();

            $prefix = '[Hubwire SKU updated] ';
            if (substr($sku->product->name, 0, strlen($prefix)) == $prefix) {
                $sku->product->name = substr($sku->product->name, strlen($prefix));
            }

            $productCols = array('name', 'description', 'description2');
            foreach ($productCols as $col) {
                $newSku->product->{$col} = $sku->product->{$col};
            }

            $sku->product->name = $prefix.$sku->product->name;
            $sku->product->save();

            $skuCols = array('client_sku', 'sku_weight');
            foreach ($skuCols as $col) {
                $newSku->{$col} = $sku->{$col};
            }

            $newSku->save();

            foreach($newSku->product->media as $m) {
                $m->forceDelete();
            }

            foreach($sku->product->media as $m) {
                $pm = new ProductMedia;
                $pm->product_id = $newSku->product->id;
                $pm->media_id = $m->media_id;
                $pm->sort_order = $m->sort_order;
                $pm->save();

                if($m->sort_order == 1) {
                    $newSku->product->default_media = $pm->id;
                }
            }

            $newSku->product->save();
        }

        // get records from DB
        $datas = DB::table('sku_mapping')->whereNotNull('new_sku_id')->whereNull('completed_at')->get();
        $skuRecords = array();
        $this->info('Preparing data...');
        foreach($datas as $data){
            $oldSku = SKU::where('sku_id', $data->sku_id)->firstOrFail();
            // get all old sku's channel sku
            $oldChannelSkus = $oldSku->channelSKUs;
            // \Log::info(print_r($oldChannelSkus, true));
            foreach($oldChannelSkus as $channelSku){
                $skuRecords[$data->id][$channelSku->channel_id]['oldChannelSku'] = $channelSku->channel_sku_id;
            }

            $newSku = SKU::where('sku_id', $data->new_sku_id)->firstOrFail();      
            // get all new sku's channel sku
            $newChannelSkus = $newSku->channelSKUs;
            foreach($newChannelSkus as $channelSku){
                $skuRecords[$data->id][$channelSku->channel_id]['newChannelSku'] = $channelSku->channel_sku_id;
            }
        }
        \Log::info(print_r($skuRecords, true));
        // how the data structure should be, you may use this to run tests. just alter that data according to your local ES
        // $skuRecords = array(
        //     //sku mapping id
        //     1 => array(
        //         // channel id
        //         10 => array(
        //             'oldChannelSku' => 70915,
        //             'newChannelSku' => 51907,
        //         ),
        //         // channel id
        //         15 => array(
        //             'oldChannelSku' => 70911666,
        //             'newChannelSku' => 888,
        //         ),
        //     ),
        //     //sku mapping id
        //     4 => array(
        //         // channel id
        //         11 => array(
        //             'oldChannelSku' => 777,
        //             'newChannelSku' => 3848,
        //         ),
        //         // channel id
        //         14 => array(
        //             // will prompt an warning due to missing old channel sku
        //             'newChannelSku' => 888,
        //         ),
        //     ),
        // );

        $this->info('Processing data...');
        // loop through records
        foreach($skuRecords as $id => $updates){
            foreach($updates as $channelId => $skuRecord){
                if(empty($skuRecord['oldChannelSku']) || empty($skuRecord['newChannelSku'])){
                    $this->info('WARNING: old channel SKU or new channel SKU is missing for sku_mapping_id :' . $id . ' & channel ID ' . $channelId . ', unable to proceed with current record; skipping record.');
                    continue;
                }
                // prepare ES header
                $cfdParams = [
                    'index' => $this->cfIndex,
                    'type' => 'data',
                    'size' => 1000,
                    'body' => [
                        'query' => [
                            'match' => [
                                'channel_sku_id' => $skuRecord['oldChannelSku']
                            ],
                        ]
                    ]
                ];
                // get response from ES
                $response = json_decode(json_encode(Es::search($cfdParams)));
                \Log::info(print_r($response, true));
                // process thru response and update all hits's channel sku id accordingly
                foreach ($response->hits->hits as $cf) {
                    $params = array();
                    $params['index'] = $this->cfIndex;
                    $params['type']  = 'data';
                    $params['id']    = $cf->_id;
                    $doc['channel_sku_id']  = $skuRecord['newChannelSku'];
                    $doc['custom_field_id'] = $cf->_source->custom_field_id;
                    $doc['field_value']     = $cf->_source->field_value;
                    $params['body']['doc']  = $doc;
                    Es::update($params);
                }
            }
        }

        foreach ($datas as $mappedSku) {
            DB::table('sku_mapping')->where('id', $mappedSku->id)->update(['completed_at' => date('Y-m-d H:i:s')]);
        }

        $this->info('Process finished.');
    }
}
