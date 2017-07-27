<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\OrderItem;
use DB;

class RemoveDuplicateChannelSku extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:removeDuplicateChannelSku';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate channel sku data due to stock transfer receive timing';

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
        $data = DB::select(DB::raw("
            select * from (
                select channel_id, product_id, sku_id, count(*) as count from channel_sku 
                where deleted_at is null
                group by channel_id, product_id, sku_id
		        order by sku_id asc
            ) tbl
            where count > 1
        "));

        foreach($data as $d) {
            $channel_id = $d->channel_id;
            $product_id = $d->product_id;
            $sku_id     = $d->sku_id;

            // $this->info('Channel #'.$channel_id.' | Product #'.$product_id.' | SKU #'.$sku_id);

            $productTp = ProductThirdParty::where('channel_id', $channel_id)->where('product_id', $product_id)->first();

            if(!empty($productTp)) {
                // $this->info('Product Third Party found! Channel #'.$channel_id.' | Product #'.$product_id);
                /* $channelSkus = ChannelSKU::where('channel_id', $channel_id)->where('sku_id', $sku_id)->get();
                $qty = [];
                $totalQty = 0;
                foreach($channelSkus as $channelSku) {
                    $qty[] = $channelSku->channel_sku_quantity;
                    $totalQty += $channelSku->channel_sku_quantity;
                    $orderItems = OrderItem::where('ref_id', $channelSku->channel_sku_id)->get();
                    if(count($orderItems)>0) $this->error('Order item found! '.$channelSku->channel_sku_id);
                }
                $this->info('Channel #'.$channel_id.' | Product #'.$product_id.' | SKU #'.$sku_id);
                $this->info(print_r($qty, true));
                $this->info('');
                $channelSku = $channelSkus[0];
                ChannelSKU::where('channel_sku_id', $channelSku->channel_sku_id)->update(['channel_sku_quantity'=>$totalQty]);
                ChannelSKU::where('channel_sku_id', '!=', $channelSku->channel_sku_id)->where('channel_id', $channel_id)
                    ->where('sku_id', $sku_id)
                    ->delete();
                // break; */
            }
            else {
                /* $channelSkus = ChannelSKU::where('channel_id', $channel_id)->where('sku_id', $sku_id)->get();
                $qty = [];
                $totalQty = 0;
                foreach($channelSkus as $channelSku) {
                    $qty[] = $channelSku->channel_sku_quantity;
                    $totalQty += $channelSku->channel_sku_quantity;
                }
                $this->info('Channel #'.$channel_id.' | Product #'.$product_id.' | SKU #'.$sku_id);
                $this->info(print_r($qty, true));
                $this->info('');
                $channelSku = $channelSkus[0];
                ChannelSKU::where('channel_sku_id', $channelSku->channel_sku_id)->update(['channel_sku_quantity'=>$totalQty]);
                ChannelSKU::where('channel_sku_id', '!=', $channelSku->channel_sku_id)->where('channel_id', $channel_id)
                    ->where('sku_id', $sku_id)
                    ->delete();
		        // break; */
            }
        }
    }
}
