<?php

namespace App\Listeners;

use App\Events\ChannelSkuQuantityChange;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\Admin\Channel;
use App\Repositories\Eloquent\SyncRepository;
use App\Model\Admin\ChannelType;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\QuantityLogApp;
use App\Models\Admin\SKUQuantityLog;

class ChannelSkuQuantityChangeListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ChannelSkuQuantityChange  $event
     * @return void
     */
    public function handle(ChannelSkuQuantityChange $event)
    {
        $chnlSku = ChannelSKU::findOrFail($event->channelSkuId);

        if($event->action == 'stockCache'){
            $data = array(
                    'channel_sku_id' => $event->channelSkuId,
                    'quantity_old' => $event->quantity,
                    'quantity_new' => $chnlSku->channel_sku_quantity,
                    'ref_table' => $event->refTable,
                    'ref_table_id' => $event->refTableId,
                    'triggered_at' => $chnlSku->updated_at,
                );
        }else{
            $oldQty = $chnlSku->channel_sku_quantity;
        
            if($event->action == 'decrement'){
                $chnlSku->decrement('channel_sku_quantity', $event->quantity);
            }elseif($event->action == 'increment'){
                $chnlSku->increment('channel_sku_quantity', $event->quantity);
            }

            $chnlSku->touch();

            $data = array(
                'channel_sku_id'    => $event->channelSkuId,
                'quantity_old'      => $oldQty,
                'quantity_new'      => $chnlSku->channel_sku_quantity,
                'ref_table'         => $event->refTable,
                'ref_table_id'      => $event->refTableId,
                'triggered_at'      => $chnlSku->updated_at,
            );
        }

        $quantity_log_app_id = QuantityLogApp::create($data)->log_id;
        $sku_quantity = ChannelSKU::where('sku_id','=',$chnlSku->sku_id)->sum('channel_sku_quantity');
        $skuQuantityLog = new SKUQuantityLog;
        $skuQuantityLog->sku_id = $chnlSku->sku_id;
        $skuQuantityLog->quantity = $sku_quantity;
        $skuQuantityLog->quantity_log_app_id = $quantity_log_app_id;
        $skuQuantityLog->save();
	
        \Log::info('ChannelSKUQuantity Change Listen');
        $sync = new SyncRepository;
        $sync->updateQuantity(['channel_sku_id'=>$event->channelSkuId]);
    }
}
