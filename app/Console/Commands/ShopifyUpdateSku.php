<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\SKU;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Modules\ThirdParty\Http\Controllers\ShopifyController;
use DB;

class ShopifyUpdateSku extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // example: php artisan Shopify:updateSku --channel_ids=13,70
    protected $signature = 'Shopify:updateSku
                            {--channel_ids= : channel id to update the sku}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update SKU to Shopify, based on table SKU_MAPPING';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Running... Shopify:updateSku');

        $channelIds = explode(',', $this->option('channel_ids'));

        $mappedSkus = DB::table('sku_mapping')->whereNull('new_sku_id')->whereNull('completed_at')->get();
        
        foreach($channelIds as $channelId) {
            $this->info('Updating Channel #'. $channelId .'...');
            $channel = Channel::findOrFail($channelId);
            $controller = new ShopifyController;
            $controller->initialize($channel);
            $shopify = $controller->api();
            $failedCount = 0;
            foreach ($mappedSkus as $mappedSku) {
                $sku = ChannelSKU::where('sku_id', $mappedSku->sku_id)->where('channel_id', $channelId)->first();

                if (!empty($sku) && !empty($sku->ref_id)) {
                    $data = array('variant' => [
                                'id'      => $sku->ref_id,
                                'sku'     => $mappedSku->new_hubwire_sku,
                                'barcode' => $mappedSku->new_hubwire_sku,
                            ]);
                    $response = $shopify('PUT', '/admin/variants/'.$sku->ref_id.'.json', $data);
                    // \Log::info($response);

                    if(!empty($response)){
                        $this->info('Updating SKU #'. $mappedSku->id .'... Done.');
                    } else {
                        $this->error('Updating SKU #'. $mappedSku->id .'... Failed.');
                        $failedCount++;
                    }
                }
            }
            $this->info('Updating Channel #'. $channelId .'... Done.');
        }

        if($failedCount <= 0) {
            foreach ($mappedSkus as $mappedSku) {
                SKU::where('sku_id', $mappedSku->sku_id)->update(['hubwire_sku' => $mappedSku->new_hubwire_sku]);
                DB::table('sku_mapping')->where('id', $mappedSku->id)->update(['completed_at' => date('Y-m-d H:i:s')]);
            }
        } else {
            $this->error('There are SKUs failed to migrate. Please check logs above.');
        }
    }
}
