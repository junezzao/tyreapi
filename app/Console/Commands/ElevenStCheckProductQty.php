<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\ProductProcessingService as ProductProc;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Services\Mailer;
use Log;

class ElevenStCheckProductQty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ElevenStreet:CheckProductQuantity
                            {--channel_id= : Channel ID}
                            {--chunk_size= : Chunk Size [int]}';

    protected $emails = array('to' =>array(
                                    'rachel@hubwire.com',
                                    'hehui@hubwire.com',
                                    'jun@hubwire.com'
                                ),
                            );

    protected $mailer;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get and compare product quantity from 11st and then creates update quantity syncs to tally system quantity with 11st.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Mailer $mailer)
    {
        parent::__construct();
        $this->error_data['subject'] = 'Error '. $this->name;
        $this->error_data['File'] = __FILE__;
        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Running... ElevenStreet:CheckProductQuantity');
        $this->info('========================================');

        $channel_type = ChannelType::where('name', '11Street')->firstOrFail();
        $channel_type_id = $channel_type->id;
        $elevStController = $channel_type->controller;

        $channel_id = $this->option('channel_id');
        $chunk_size = $this->option('chunk_size');

        if(is_null($chunk_size)){
            $chunk_size = 30;
        }

        $emailData = array();

        // Get all 11st active channels
        $channels = Channel::where('channel_type_id', $channel_type_id)->where('status', 'Active');
        if(!is_null($channel_id))
        {
            $channels = $channels->where('id', $channel_id);
        }
        $channels = $channels->get();

        try
        {
            foreach($channels as $channel)
            {
                $this->info('Going through products in channel [' . $channel->id . ']');

                $productProc = new ProductProc($channel->id, new $elevStController);

                $hwProducts = Product::leftJoin('channel_sku', 'products.id', '=', 'channel_sku.product_id')
                                        ->where('channel_sku.channel_id', $channel->id)
                                        ->whereNull('channel_sku.deleted_at')
                                        ->whereNull('products.deleted_at')
                                        ->get();

                $elevenStProducts = Product::leftJoin('product_third_party', 'products.id', '=', 'product_third_party.product_id')
                                        ->where('product_third_party.channel_id', $channel->id)
                                        ->whereNull('products.deleted_at')
                                        ->get();

                $hwProductList = array();
                $elevenStProductList = array();
                $elevenStResponseProducts = array();
                $qtyMismatchId = array();
                $elevenStResponse = array();
                $errorProdIds = array();
                $errorFlag = false;

                // Log::info(print_r($hwProducts->toArray(), true));

                foreach($elevenStProducts as $elevenStProduct){
                    if(!is_null($elevenStProduct->ref_id)){
                        // commented due to slow performance in retrieving 1 by 1 
                        // $elevenStProductList[$elevenStProduct->ref_id] = $productProc->getProductStock($elevenStProduct->ref_id);
                        $elevenStProductList[] = $elevenStProduct->ref_id;
                    }
                }

                $counter = count($elevenStProductList);

                $this->info('Total number of products to pull from 11st :' . $counter);

                // Get product stock from 11st in chunks
                $tpProdIds = array_chunk($elevenStProductList, $chunk_size);
                $this->info('[' . date("d/m/Y H:i:s") . '] Started pulling products from 11st.');
                foreach($tpProdIds as $chunk){
                    //foreach($chunk as $prodIds){
                        $counter -= count($chunk);
                        $this->info('[' . date("d/m/Y H:i:s") . '] Getting '.count($chunk).' products info from 11st.. ('.$counter.' product(s) left)');
                        $elevenStResponse = $productProc->getProductsQty($chunk);
                        foreach($elevenStResponse as $response){
                            $elevenStResponseProducts[] = $response;
                        }
                    //}
                }

                $this->info('[' . date("d/m/Y H:i:s") . '] Pulling products from 11st ended.');

                // \Log::info($elevenStResponseProducts);
                // dd();
                foreach($hwProducts as $hwProduct){
                    if(!is_null($hwProduct->ref_id)){
                        $hwProductList[$hwProduct->ref_id] = array(
                            'qty'           => $hwProduct->channel_sku_quantity,
                            'chnl_sku_id'   => $hwProduct->channel_sku_id,
                        );
                    }
                }
                $this->info('|---------------------+------------------------+--------------------+-----------------+-------------|');
                $this->info('| Hubwire Chnl SKU ID | Third Party Product ID | Third Party SKU ID | Third Party Qty | Hubwire Qty |');
                $this->info('|---------------------+------------------------+--------------------+-----------------+-------------|');

                foreach($elevenStResponseProducts as $index => $record){
                    if(isset($hwProductList[$record['chnl_sku_ref_id']])){
                        if($record['stock_qty'] != $hwProductList[$record['chnl_sku_ref_id']]['qty']){
                            if($record['stock_qty'] == 0 && $hwProductList[$record['chnl_sku_ref_id']]['qty'] < 0){
                                // do nothing
                            }else{
                                $emailData[] = array(
                                    'hwChnlSkuId'       => $hwProductList[$record['chnl_sku_ref_id']]['chnl_sku_id'],
                                    'tpQty'             => $record['stock_qty'],
                                    'hwQty'             => $hwProductList[$record['chnl_sku_ref_id']]['qty'],
                                );
                                $qtyMismatchId[] = $hwProductList[$record['chnl_sku_ref_id']]['chnl_sku_id'];
                                $this->info('| ' . sprintf("%' 19s", $hwProductList[$record['chnl_sku_ref_id']]['chnl_sku_id']) . ' | ' . sprintf("%' 22s", $record['product_ref_id']) . ' | ' . sprintf("%' 18s", $record['chnl_sku_ref_id']) . ' | '  . sprintf("%' 15s", $record['stock_qty']) . ' | ' . sprintf("%' 11s", $hwProductList[$record['chnl_sku_ref_id']]['qty']) . ' | ');
                                $this->info('|---------------------+------------------------+--------------------+-----------------+-------------|');
                                $errorFlag = true;
                            }
                        }
                        unset($elevenStResponseProducts[$index]);   
                    }
                }

                if(!$errorFlag){
                    $this->info('No qty mismatches found.');
                }else{
                    $this->info('List of mismatched chnl_sku_id\'s qty:');
                    $mismatchIds = implode(',', $qtyMismatchId);
                    $this->info($mismatchIds);
                    \Log::info($mismatchIds);
                    $this->info('Creating update quantity syncs');
                    $this->call('ThirdParty:updateQuantity', [
                        '--channel_sku_ids' => $mismatchIds,
                    ]);
                }

                if(count($elevenStResponseProducts) > 0){
                    $this->info('Warning, there are the product details that are not registered in our system. Details are stored into log file.');
                    \Log::info('Products that are not registered: ' . print_r($elevenStResponseProducts, true));
                }

                $this->info('End');

            }
            if(count($emailData) > 0){
                $data = $this->emails;
                $data['skuData'] = $emailData;
                $data['channel'] = 'ElevenStreet';
                $this->mailer->qtyCheckNotification($data);
            }

            $this->info('Finished!');
        }
        catch(Exception $e)
        {
            $this->error_data['Command'] = $this->name;
            $this->error_data['ErrorDescription'] = 'Error: '.$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
            $this->error($this->error_data['ErrorDescription']);
            \Log::error($this->error_data['ErrorDescription']);
        }

    }
}
