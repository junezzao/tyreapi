<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\ProductProcessingService as ProductProc;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Services\Mailer;

class SellerCenterCheckProductQty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SellerCenter:CheckProductQuantity
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
    protected $description = 'Get and compare product quantity from Seller Center and then creates update quantity syncs to tally system quantity with Seller Center.';

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
        $this->info('Running... SellerCenter:CheckProductQuantity');
        $this->info('========================================');

        $channel_types = ChannelType::whereIn('name', ['Lazada','Zalora'])->get()->pluck('id');

        $channel_id = $this->option('channel_id');
        $chunk_size = $this->option('chunk_size');

        if(is_null($chunk_size)){
            $chunk_size = 30;
        }

        // Get all SellerCenter active channels
        $channels = Channel::whereIn('channel_type_id', $channel_types)->where('status', 'Active');
        if(!is_null($channel_id))
        {
            $channels = $channels->where('id', $channel_id);
        }
        $channels = $channels->get();

        $emailData = array();

        try{
            foreach($channels as $channel){
                $this->info('Going through products in channel [' . $channel->id . ']');
                $channelController = ChannelType::where('id', $channel->channel_type_id)->value('controller');
                // \Log::info($channelController);
                $productProc = new ProductProc($channel->id, new $channelController);

                // get channel skus
                $hwSkus = ChannelSKU::select('channel_sku.channel_sku_id','channel_sku.channel_sku_quantity', 'sku.hubwire_sku')->where('channel_sku.channel_id', $channel->id)
                                        ->leftJoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')->get();

                $hwSkuList = array();
                $scSkuList = array();
                $scResponseProducts = array();
                $qtyMismatchId = array();
                $scResponse = array();
                $errorProdIds = array();
                $errorFlag = false;

                foreach($hwSkus as $hwSku){
                    $hwSkuList[$hwSku->hubwire_sku] = array(
                        'qty'       => $hwSku->channel_sku_quantity,
                        'chnlSku'   => $hwSku->hubwire_sku,
                        'chnlSkuId' => $hwSku->channel_sku_id,
                        'status'    => $hwSku->channel_sku_active
                    );
                    $scSkuList[] = $hwSku->hubwire_sku;
                }

                $counter = count($scSkuList);

                $this->info('Total number of products to pull from SellerCenter :' . $counter);

                // Get product stock from SellerCenter in chunks
                $chunkedSkus = array_chunk($scSkuList, $chunk_size);
                $this->info('[' . date("d/m/Y H:i:s") . '] Started pulling products from SellerCenter.');

                foreach($chunkedSkus as $chunk){
                    $counter -= count($chunk);
                    $this->info('[' . date("d/m/Y H:i:s") . '] Getting '.count($chunk).' products info from Seller Center.. ('.$counter.' product(s) left)');
                    $scResponse = $productProc->getProductsQty($chunk);
                    foreach($scResponse as $response){
                        $scResponseProducts[] = $response;
                    }
                }

                $this->info('[' . date("d/m/Y H:i:s") . '] Pulling products from SellerCenter ended.');

                $this->info('|-------------------------------+------------------------------------+-----------------+-------------|');
                $this->info('|       Hubwire Chnl SKU        |       Third Party Product SKU      | Third Party Qty | Hubwire Qty |');
                $this->info('|-------------------------------+------------------------------------+-----------------+-------------|');

                foreach($scResponseProducts as $index => $record){
                    if(isset($hwSkuList[$record['chnlSku']])){
                        if($record['stock_qty'] != $hwSkuList[$record['chnlSku']]['qty']){
                            if($record['stock_qty'] == 0 && $hwSkuList[$record['chnlSku']]['qty'] < 0){
                                // do nothing
                            }elseif($record['status'] == 'inactive' && $hwSkuList[$record['chnlSku']]['status'] == 0){
				// do nothing
                            }
                            // elseif($record['stock_qty'] < $hwSkuList[$record['chnlSku']]['qty']){
                                // do nothing
                            // }
                            else{
                                $emailData[] = array(
                                    'hwChnlSkuId'       => $hwSkuList[$record['chnlSku']]['chnlSkuId'],
                                    'tpQty'             => $record['stock_qty'],
                                    'hwQty'             => $hwSkuList[$record['chnlSku']]['qty'],
                                );
                                $qtyMismatchId[] = $hwSkuList[$record['chnlSku']]['chnlSkuId'];
                                $this->info('| ' . sprintf("%' 29s", $hwSkuList[$record['chnlSku']]['chnlSku']) . ' | ' . sprintf("%' 34s", $record['chnlSku']) . ' | ' . sprintf("%' 15s", $record['stock_qty']) . ' | ' . sprintf("%' 11s", $hwSkuList[$record['chnlSku']]['qty']) . ' | ');
                                $this->info('|-------------------------------+------------------------------------+-----------------+-------------|');
                                $errorFlag = true;
                            }
                        }
                        unset($scResponseProducts[$index]);   
                    }
                }

                if(!$errorFlag){
                    $this->info('No qty mismatches found.');
                }else{
                    $this->info('List of mismatched chnl_sku_id\'s qty:');
                    $mismatchIds = implode(',', $qtyMismatchId);
                    $this->info($mismatchIds);
                    \Log::info($mismatchIds);
                    /*$this->info('Creating update quantity syncs');
                    $this->call('ThirdParty:updateQuantity', [
                        '--channel_sku_ids' => $mismatchIds,
                    ]);*/
                }

                if(count($scResponseProducts) > 0){
                    $this->info('Warning, there are the product details that are not registered in our system. Details are stored into log file.');
                    \Log::info('Product SKU(s) that are not registered: ' . print_r($scResponseProducts, true));
                }

                $this->info('End');

            }

            if(count($emailData) > 0){
                $data = $this->emails;
                $data['skuData'] = $emailData;
                $data['channel'] = 'Seller Center';
                //$this->mailer->qtyCheckNotification($data);
            }

            $this->info('Finished!');

        }catch(Exception $e){
            $this->error_data['Command'] = $this->name;
            $this->error_data['ErrorDescription'] = 'Error: '.$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
            $this->error($this->error_data['ErrorDescription']);
            \Log::error($this->error_data['ErrorDescription']);
        }
        // testing
        // $zaloraController = 'ZaloraController';
        // $channel_id = $this->option('channel_id');
        // $productProc = new ProductProc($channel_id, new $zaloraController);
        // $test = $productProc->getProductsQty(['32164PLH013483-05-30', '32165PLH013483-05-32']);
        // \Log::info($test);
    }
}
