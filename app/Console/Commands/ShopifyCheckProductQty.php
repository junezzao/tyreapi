<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\ProductProcessingService as ProductProc;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Services\Mailer;

class ShopifyCheckProductQty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Shopify:CheckProductQuantity
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
    protected $description = 'Get and compare product quantity from Shopify and then creates update quantity syncs to tally system quantity with Shopify.';

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
        $this->info('Running... Shopify:CheckProductQuantity');
        $this->info('========================================');

        $channel_type = ChannelType::whereIn('name', ['Shopify','Shopify POS'])->get();
        foreach ($channel_type as $key => $value) {
            $channel_type_id[$value->id] = $value->id;
            $shopifyController = $value->controller;//both use the same controller
        }
        //$channel_type_id = $channel_type->id;
        //$shopifyController = $channel_type->controller;

        $channel_id = $this->option('channel_id');
        $chunk_size = $this->option('chunk_size');

        if(is_null($chunk_size)){
            $chunk_size = 30;
        }

        // Get all Shopify active channels
        $channels = Channel::whereIn('channel_type_id', $channel_type_id)->where('status', 'Active');
        if(!is_null($channel_id))
        {
            $channels = $channels->where('id', $channel_id);
        }
        $channels = $channels->get();


        $emailData = array();

        try
        {
            foreach($channels as $channel)
            {
                $this->info('Going through products in channel [' . $channel->id . ']');

                $productProc = new ProductProc($channel->id, new $shopifyController);

                $hwProducts = Product::leftJoin('channel_sku', 'products.id', '=', 'channel_sku.product_id')
                                        ->where('channel_sku.channel_id', $channel->id)
                                        ->whereNull('channel_sku.deleted_at')
                                        ->whereNull('products.deleted_at')
                                        ->get();

                $shopifyProducts = Product::leftJoin('product_third_party', 'products.id', '=', 'product_third_party.product_id')
                                        ->where('product_third_party.channel_id', $channel->id)
                                        ->whereNull('products.deleted_at')
                                        ->get();

                $hwProductList = array();
                $shopifyProductList = array();
                $shopifyResponseProducts = array();
                $qtyMismatchId = array();
                $shopifyResponse = array();
                $errorProdIds = array();
                $errorFlag = false;

                // Log::info(print_r($hwProducts->toArray(), true));

                foreach($shopifyProducts as $shopifyProduct){
                    if(!is_null($shopifyProduct->ref_id)){
                        // commented due to slow performance in retrieving 1 by 1 
                        // $shopifyProductList[$shopifyProduct->ref_id] = $productProc->getProductStock($shopifyProduct->ref_id);
                        $shopifyProductList[] = $shopifyProduct->ref_id;
                    }
                }

                $counter = count($shopifyProductList);

                $this->info('Total number of products to pull from Shopify :' . $counter);

                // Get product stock from Shopify in chunks
                $tpProdIds = array_chunk($shopifyProductList, $chunk_size);
                $this->info('[' . date("d/m/Y H:i:s") . '] Started pulling products from Shopify.');
                foreach($tpProdIds as $chunk){
                    //foreach($chunk as $prodIds){
                        $counter -= count($chunk);
                        $this->info('[' . date("d/m/Y H:i:s") . '] Getting '.count($chunk).' products info from Shopify.. ('.$counter.' product(s) left)');
                        $shopifyResponse = $productProc->getProductsQty($chunk);
                        foreach($shopifyResponse as $response){
                            $shopifyResponseProducts[] = $response;
                        }
                    //}
                }

                $this->info('[' . date("d/m/Y H:i:s") . '] Pulling products from Shopify ended.');

                // \Log::info($shopifyResponseProducts);
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

                foreach($shopifyResponseProducts as $index => $record){
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
                        unset($shopifyResponseProducts[$index]);   
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

                if(count($shopifyResponseProducts) > 0){
                    $this->info('Warning, there are the product details that are not registered in our system. Details are stored into log file.');
                    \Log::info('Products that are not registered: ' . print_r($shopifyResponseProducts, true));
                }

                $this->info('End');

            }

            if(count($emailData) > 0){
                $data = $this->emails;
                $data['skuData'] = $emailData;
                $data['channel'] = 'Shopify';
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

        // testing
        // $shopifyController = 'ShopifyController';
        // $channel_id = $this->option('channel_id');
        // $productProc = new ProductProc($channel_id, new $shopifyController);
        // $test = $productProc->getProductsQty([5365726849,8078359809]);
        // \Log::info($test);
    }
}
