<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\ThirdPartyController;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ChannelSKU;
use Log;
use DB;

class SyncThirdParty extends Command
{
    const SELLER_CENTER_FEED_LIMIT = 50;
    const SELLER_CENTER_BULK_SYNC_LIMIT = 100;
    const REQUEST_LIMIT = array(
        'Shopify'           => 40,
        'Lelong'            => 10,
        'Lazada'            => 25,
        'LazadaSC'          => 30,
        'Zalora'            => 25,
        '11Street'          => 40,
        'Storefront Vendor' => 100,
        'RubberNeck' => 100,
        'BearInBag' => 100,
        'AmaxMall' => 100,
        'Shopify POS'  => 40,
    );

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ThirdParty:sync
                            {--merchant_id= : Merchant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data to third parties';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->error_data['subject'] = 'Error '. $this->name;
        $this->error_data['File'] = __FILE__;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('========================== START SYNC ==============================');

        try{
            $this->info('Running...'. $this->name);
            $sellerCenters = array();
            $scChannelTypes = ChannelType::select('id', 'name')->whereIn('name', ['Lazada','Zalora'])->get();

            foreach($scChannelTypes as $scChannelType) {
                $sellerCenters[$scChannelType->id] = $scChannelType->name;
            }

            $merchantId = $this->option('merchant_id');
            $tpc = new ThirdPartyController;

            $counters = array(
                'Shopify'           => 0,
                'Shopify POS'       => 0,
                'Lelong'            => 0,
                'Lazada'            => 0,
                'LazadaSC'          => 0,
                'Zalora'            => 0,
                '11Street'          => 0,
                'Storefront Vendor' => 0,
                'RubberNeck'        => 0,
                'BearInBag'         => 0,
                'AmaxMall'          => 0,
            );

            // process feeds for Seller Center
            $this->processFeed($tpc, $counters, $sellerCenters);

            $counters['Lazada'] = ((self::SELLER_CENTER_FEED_LIMIT - $counters['Lazada']) < self::REQUEST_LIMIT['Lazada']) ? (self::REQUEST_LIMIT['Lazada'] - (self::SELLER_CENTER_FEED_LIMIT - $counters['Lazada'])) : 0;
            $counters['Zalora'] = ((self::SELLER_CENTER_FEED_LIMIT - $counters['Zalora']) < self::REQUEST_LIMIT['Zalora']) ? (self::REQUEST_LIMIT['Zalora'] - (self::SELLER_CENTER_FEED_LIMIT - $counters['Zalora'])) : 0;

            $this->processSingleSyncs($tpc, $counters, $sellerCenters, $merchantId);

            $this->processSyncsInBulk($tpc, $counters, $sellerCenters, $merchantId);

        }
        catch(Exception $e)
        {
            $this->error_data['Command'] = $this->name;
            $this->error_data['ErrorDescription'] = 'Error: '. $e->getMessage() .' in '.$e->getFile().' at line '. $e->getLine();
            $this->error($this->error_data['ErrorDescription']);
            Log::error($this->error_data['ErrorDescription']);
            $tpc->ErrorAlert($this->error_data);
        }

    }

    /**
     * Process feed from Seller Center, only applicable for Lazada and Zalora
     */
    protected function processFeed(ThirdPartyController $tpc, &$counters, $sellerCenters){
        Log::info('========================= Process Feeds ============================');
        $allCounter = 0;
        $bulkSyncs = array();
        // $channelCounter = array();

        // $channel_types = ChannelType::whereIn('name', ['Lazada', 'Zalora'])->get();
        $syncs = ThirdPartySync::where(function($query){
                $query->where('status', 'QUEUED')
                    ->orWhere('status', 'PROCESSING')
                    ->orWhere('status', 'SENT');
            })
            ->whereIn('channel_type_id', array_keys($sellerCenters))
            // ->where('channel_type_id', $channel_type->id)
            ->orderBy('channel_type_id')
            ->orderBy('channel_id')
            ->orderBy('request_id')
            ->get();

        $bulkSyncs = array();

        foreach($syncs as $sync)
        {
            $bulkSyncs[$sync->channel_type_id][$sync->channel_id][$sync->request_id][$sync->id] = $sync;
        }

        foreach($bulkSyncs as $channelTypeId => $channelTypeBulk)
        {
            foreach($channelTypeBulk as $channelId => $channelBulk)
            {
                foreach($channelBulk as $feedId => $feedBulk)
                {
                    $bulkSync = array();
                    $bulkSync['channelTypeId'] = $channelTypeId;
                    $bulkSync['channelId'] = $channelId;
                    $bulkSync['feedId'] = $feedId;
                    $bulkSync['syncs'] = $feedBulk;
                    if($counters[$sellerCenters[$channelTypeId]] >= self::SELLER_CENTER_FEED_LIMIT){
                        sleep(3); // reset
                        $counters[$sellerCenters[$channelTypeId]] = 0;
                    }
                    $tpc->bulkFeedStatus($bulkSync);
                    $counters[$sellerCenters[$channelTypeId]]++;
                    $allCounter++;
                }
            }
        }

        Log::info('Created and proccesed ['.$allCounter.'] bulk syncs from ['.$syncs->count().'] syncs in DB.');
        // Old code
        // foreach($channel_types as $channel_type)
        // {
        //     $bulkSyncs = array();
        //     $syncs = ThirdPartySync::where(function($query){
        //                     $query->where('status', 'QUEUED')
        //                         ->orWhere('status', 'PROCESSING')
        //                         ->orWhere('status', 'SENT');
        //                 })
        //                 ->where('channel_type_id', $channel_type->id)
        //                 ->get();

        //     Log::info($syncs->count() .' record(s) found for '. $channel_type->name);

        //     foreach($syncs as $sync)
        //     {
        //         if ($counters[$channel_type->name] >= self::SELLER_CENTER_FEED_LIMIT) {
        //             sleep(3); // reset
        //             $counters[$channel_type->name] = 0;
        //         }

        //         $tpc->feedStatus($sync);
        //         $counters[$channel_type->name]++;
        //     }

        //     foreach ($syncs as $sync)
        //     {
        //         ThirdPartySync::updateSyncStatus($sync);
        //     }
        // }
    }

    protected function processSingleSyncs(ThirdPartyController $tpc, &$counters, $sellerCenters, $merchantId = null){
        Log::info('========================= Process Single Syncs ============================');

        $syncs = ThirdPartySync::select('third_party_sync.*', 'channel_types.name as channel_type', 'channels.name as channel_name', DB::raw('(case when action = "updateMedia" then 1 else 0 end) as seq'))
                    ->leftJoin('channels', 'third_party_sync.channel_id', '=', 'channels.id')
                    ->leftJoin('channel_types', 'channels.channel_type_id', '=', 'channel_types.id')
                    ->where('third_party_sync.sync_type', 'auto')
                    ->where('channels.status', 'Active')
                    ->where(function($query) {
                        $query->where('third_party_sync.status', 'RETRY')
                            ->orWhere('third_party_sync.status', 'NEW')
                            ->orWhere('third_party_sync.status', 'SCHEDULED');
                    })
                    ->whereNotIn('third_party_sync.channel_type_id', array_keys($sellerCenters));

        if(!is_null($merchantId))
        {
            $syncs->where('third_party_sync.merchant_id', $merchantId);
        }
        $syncs = $syncs->orderBy('seq', 'asc')->get();

        Log::info($syncs->count().' record(s) found.');

        $queued_syncs = array();
        foreach($syncs as $sync)
        {
            if(!isset($counters[$sync['channel_type']])) continue;

	       $proceed = ($counters[$sync['channel_type']] < self::REQUEST_LIMIT[$sync['channel_type']]) ? true : false;

            if ($proceed) {
                $queued_syncs[] = $sync;
                $counters[$sync['channel_type']]++;
            }
        }

        foreach($queued_syncs as $sync)
        {
            $clientName = preg_match('/(\[.*?\])/', $sync->channel_name, $matches);
            try {
                if( env('APP_ENV') == 'production' || (env('APP_ENV') != 'production' && (isset($matches[0]) && $matches[0] == '[Development Client]')) )
                {
                    Log::info('processing sync#'.$sync->id);
                    
                    $tpc->processSync($sync);
                }
            } catch(Exception $e) {
                /***** IMPORTANT NOTE *****/
                // This is to ensure that the sync goes on even in the VERY VERY unfortunate event of unexpected exceptions.
                // Exception is not supposed to happen here! On top of fixing the error reported here, make sure exceptions handled within try & catch inside ThirdPartyController...
                $this->error_data['Command'] = $this->name;
                $this->error_data['ErrorDescription'] =' Error was not handled properly. Note for Tech : try and catch in function please .. Exception Message:'. $e->getMessage() .' in '. $e->getFile() .' line '. $e->getLine();
                $this->error('Sync Error encountered ! please add try and catch in the function called !');
                $this->error($this->error_data['ErrorDescription']);

                // mark sync as FAILED
                $sync->status = 'FAILED';
                $sync->remarks = $this->error_data['ErrorDescription'];
                $sync->save();

                $tpc->ErrorAlert($this->error_data);
            }
        }

        Log::info('Updating sync statuses (indicator) ... ...');
        foreach($queued_syncs as $sync)
        {
            Log::info('Running... sync#'.$sync->id);
            ThirdPartySync::updateSyncStatus($sync);
        }

        $msg = 'Syncs processed: ';
        foreach ($counters as $channel_type => $count)
        {
            $msg .= $channel_type . ' >> ' . $count . ' | ';
        }

        Log::info($msg);
    }

    protected function processSyncsInBulk(ThirdPartyController $tpc, &$counters, $sellerCenters, $merchantId = null){
        Log::info('=============== Process Syncs in Bulks for SellerCenter ==================');

        $scSyncs = ThirdPartySync::select('third_party_sync.*', 'channel_types.name as channel_type', 'channels.name as channel_name', DB::raw('(case when action = "updateMedia" then 1 else 0 end) as seq'))
                    ->leftJoin('channels', 'third_party_sync.channel_id', '=', 'channels.id')
                    ->leftJoin('channel_types', 'channels.channel_type_id', '=', 'channel_types.id')
                    ->where('third_party_sync.sync_type', 'auto')
                    ->where('channels.status', 'Active')
                    ->where(function($query) {
                        $query->where('third_party_sync.status', 'RETRY')
                            ->orWhere('third_party_sync.status', 'NEW')
                            ->orWhere('third_party_sync.status', 'SCHEDULED');
                    })
                    ->whereIn('third_party_sync.channel_type_id', array_keys($sellerCenters));

        if(!is_null($merchantId))
        {
            $scSyncs->where('third_party_sync.merchant_id', $merchantId);
        }

        $scSyncs = $scSyncs->orderBy('seq', 'asc')->get();
        //\Log::info(print_r($scSyncs->toArray(), true));
        //dd();
        $bulkSyncs = array();
        $counter = 0;
        $createAction = array('createProduct', 'createSKU');
        $updateAction = array('updateProduct', 'updateSKU');
        $qtyAction = array('updateQuantity');
        $priceAction = array('updatePrice');
        $mediaAction = array('uploadNewMedia', 'setDefaultMedia', 'updateMedia', 'deleteMedia');

        foreach($scSyncs as $sync){
            $clientName = preg_match('/(\[.*?\])/', $sync->channel_name, $matches);
            if( env('APP_ENV') == 'production' || (env('APP_ENV') != 'production' && (isset($matches[0]) && $matches[0] == '[Development Client]')) ){
                Log::info('processing sync#'.$sync->id.' as bulk');
            } else {
                continue;
            }

            if($sync->ref_table == 'Product'){
                // get sellerSKUs (HW SKUs) through product
                $sellerSkus = ChannelSKU::select('channel_sku.channel_sku_id', 'channel_sku.sku_id', 'channel_sku.channel_id', 'channel_sku.product_id', 'sku.sku_id')
                                ->leftJoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                                ->where('channel_sku.channel_id', $sync->channel_id)
                                ->where('channel_sku.product_id', $sync->ref_table_id)
                                ->orderBy('sku.sku_id', 'ASC')
                                ->get();

                foreach($sellerSkus as $sellerSku){
                    // each bulk should be grouped into create, update, and media
                    if(in_array($sync->action, $mediaAction)){
                        $sync->channel_sku_id = $sellerSku->channel_sku_id;
                        $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkMedia'][$sellerSku->sku_id][] = clone $sync;
                    }elseif(in_array($sync->action, $createAction)){
                        $sync->channel_sku_id = $sellerSku->channel_sku_id;
                        $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkCreate'][$sellerSku->sku_id][] = clone $sync;
                    }elseif(in_array($sync->action, $updateAction)){
                        $sync->channel_sku_id = $sellerSku->channel_sku_id;
                        $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkUpdate'][$sellerSku->sku_id][] = clone $sync;
                    }elseif(in_array($sync->action, $qtyAction)){
                        $sync->channel_sku_id = $sellerSku->channel_sku_id;
                        $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkQty'][$sellerSku->sku_id][] = clone $sync;
                    }
                }
            }else if($sync->ref_table == 'ChannelSKU'){
                // get sellerSKU (HW SKU) through channel SKU
                $sellerSku = ChannelSKU::select('channel_sku.channel_sku_id', 'channel_sku.sku_id', 'channel_sku.channel_id', 'channel_sku.product_id', 'sku.sku_id')
                                ->leftJoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                                ->where('channel_sku.channel_id', $sync->channel_id)
                                ->where('channel_sku.channel_sku_id', $sync->ref_table_id)
                                ->first();
                // \Log::info(print_r($sellerSku->toArray(), true));

                if(in_array($sync->action, $mediaAction)){
                    $sync->channel_sku_id = $sellerSku->channel_sku_id;
                    $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkMedia'][$sellerSku->sku_id][] = clone $sync;
                }elseif(in_array($sync->action, $createAction)){
                    $sync->channel_sku_id = $sellerSku->channel_sku_id;
                    $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkCreate'][$sellerSku->sku_id][] = clone $sync;
                }elseif(in_array($sync->action, $updateAction)){
                    $sync->channel_sku_id = $sellerSku->channel_sku_id;
                    $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkUpdate'][$sellerSku->sku_id][] = clone $sync;
                }elseif(in_array($sync->action, $qtyAction)){
                    $sync->channel_sku_id = $sellerSku->channel_sku_id;
                    $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkQty'][$sellerSku->sku_id][] = clone $sync;
                }elseif(in_array($sync->action, $priceAction)){
                    $sync->channel_sku_id = $sellerSku->channel_sku_id;
                    $bulkSyncs[$sync->channel_type_id][$sync->channel_id]['bulkPrice'][$sellerSku->sku_id][] = clone $sync;
                }
            }
        }

        foreach($bulkSyncs as $channelTypeId => $channelTypeSyncs){
            foreach($channelTypeSyncs as $channelId => $channelSyncs){
                foreach($channelSyncs as $action => $syncActions){
                    // do while loop to structure the bulk sync to be sent to tpc
                    do{
                        // reset continue array for each do while loop iteration
                        $continue = array();
                        if($counters[$sellerCenters[$channelTypeId]] > self::REQUEST_LIMIT[$sellerCenters[$channelTypeId]]){
                            continue;
                        }

                        // reset bulk sync after each iteration
                        $bulkSync = array();
                        // attach relevant information to the bulk sync
                        $bulkSync['channelTypeId'] = $channelTypeId;
                        $bulkSync['channelId'] = $channelId;
                        $bulkSync['action'] = $action;
                        foreach($syncActions as $skuId => $sync){
                            // check if bulk sync has exceeded limit or not
                            if(!isset($bulkSync['syncData']) || count($bulkSync['syncData']) < self::SELLER_CENTER_BULK_SYNC_LIMIT){
                                $chnlSkuId = $syncActions[$skuId][0]->channel_sku_id;
                                unset($syncActions[$skuId][0]->channel_sku_id);
                                $bulkSync['syncData'][] = array(
                                    'skuId' => $skuId,
                                    'sync' => array_shift($syncActions[$skuId]),
                                    'chnlSkuId' => $chnlSkuId,
                                );
                                // check if is there any elements left in the array, if no, unset the index in $syncActions array
                                if(count($syncActions[$skuId]) <= 0){
                                    unset($syncActions[$skuId]);
                                    $continue[] = 0;
                                }else{
                                    // there are still elements in the $syncAction array, hence it to 1 to continue do while loop
                                    $continue[] = 1;
                                }
                            }else{
                                // exceeded max chunk value, set continue to 1 to continue do while loop
                                $continue[] = 1;
                            }
                        }
                        // send to TPC to process the bulk sync
                        try {
                            Log::info('processing bulk sync #'.($counter+1));
                            $tpc->processSyncInBulk($bulkSync);

                        } catch(Exception $e) {
                            /***** IMPORTANT NOTE *****/
                            // This is to ensure that the sync goes on even in the VERY VERY unfortunate event of unexpected exceptions.
                            // Exception is not supposed to happen here! On top of fixing the error reported here, make sure exceptions handled within try & catch inside ThirdPartyController...
                            $this->error_data['Command'] = $this->name;
                            $this->error_data['ErrorDescription'] =' Error was not handled properly. Note for Tech : try and catch in function please .. Exception Message:'. $e->getMessage() .' in '. $e->getFile() .' line '. $e->getLine();
                            $this->error('Sync Error encountered ! please add try and catch in the function called !');
                            $this->error($this->error_data['ErrorDescription']);

                            $tempSyncs = array();

                            foreach($bulkSync['syncData'] as $data){
                                // check to prevent updating same sync over and over again
                                if(!in_array($data['sync']->id, $tempSyncs)){
                                    $data['sync']->status = 'FAILED';
                                    $data['sync']->remarks = $this->error_data['ErrorDescription'];
                                    $data['sync']->save();
                                    // push sync id to array to indicate this sync has already been updated to fail
                                    $tempSyncs[] = $data['sync']->id;
                                }
                            }
                            $tpc->ErrorAlert($this->error_data);
                        }
                        $counters[$sellerCenters[$channelTypeId]]++;
                        $counter++;
                    }while(in_array(1, $continue)); // continue looping as long as there are still elements in the array

                }
            }
        }

        $msg = 'Syncs processed: ';
        foreach ($counters as $channel_type => $count)
        {
            $msg .= $channel_type . ' >> ' . $count . ' | ';
        }

        Log::info($msg);
        Log::info('Created and proccesed ['.$counter.'] bulk syncs from ['.$scSyncs->count().'] syncs in DB.');
    }
}
