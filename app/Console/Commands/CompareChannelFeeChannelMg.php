<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ThirdPartyReport;
use App\Models\Admin\ChannelType;

class CompareChannelFeeChannelMg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:compareChannelFeeChannelMg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare total channel fee vs channel mg and pick whichever higher.';

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
        // check for all shopify and shopify POS channels only
        $shopify = ChannelType::whereIn('name', ['Shopify', 'Shopify POS'])->get(['id']);
        $tpOrderItemIds = ThirdPartyReport::select(\DB::raw('distinct order_item_id'))
                                ->whereNotNull('order_item_id')
                                ->where('status', '!=', 'Completed')
                                ->whereIn('channel_type_id', $shopify->toArray())
                                ->pluck('order_item_id');

        foreach ($tpOrderItemIds as $tpOrderItemId) {
            $orderItem = OrderItem::find($tpOrderItemId);
            $arrayName = $orderItem->ref->product->brand_id.'-'.$orderItem->order->channel_id;
            if (isset($calTotal[$arrayName]['totalMg']) && isset($calTotal[$arrayName]['totalFee'])) {
                $calTotal[$arrayName]['totalMg']    += $orderItem->channel_mg;
                $calTotal[$arrayName]['totalFee']   += $orderItem->channel_fee;
            }else{
                $calTotal[$arrayName]['totalMg']    = $orderItem->channel_mg;
                $calTotal[$arrayName]['totalFee']   = $orderItem->channel_fee;
            }
            $calTotal[$arrayName]['order_item_id'][$orderItem->id]['channel_mg'] = $orderItem->channel_mg;
            $calTotal[$arrayName]['order_item_id'][$orderItem->id]['channel_fee'] = $orderItem->channel_fee;
        }

        foreach ($calTotal as $brandChannel) {
            foreach ($brandChannel['order_item_id'] as $key => $itemValue) {
                $tp = ThirdPartyReport::where('order_item_id', '=', $key)->first();
                $tpReportChannelFee = number_format(round(($brandChannel['totalFee'] > $brandChannel['totalMg'] ? $itemValue['channel_fee'] : $itemValue['channel_mg']), 2), 2);
                ThirdPartyReport::where('order_item_id', '=', $key)->update(['channel_fees' => $tpReportChannelFee]);
                $this->info('Updating Channel Fee for third party item id '.$tp->id.' from '.$tp->channel_fees.' to '.$tpReportChannelFee);
                \Log::info('Updating Channel Fee for third party item id '.$tp->id.' from '.$tp->channel_fees.' to '.$tpReportChannelFee);
            }
        }
    }
}