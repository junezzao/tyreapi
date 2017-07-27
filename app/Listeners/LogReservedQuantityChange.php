<?php

namespace App\Listeners;

use App\Events\ReservedQuantityChange;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\ReservedQuantityLog;

class LogReservedQuantityChange
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
     * @param  ReservedQuantityChange  $event
     * @return void
     */
    public function handle(ReservedQuantityChange $event)
    {
        $reservedQuantity = ReservedQuantity::where('channel_sku_id', $event->channelSkuId)->firstOrFail();
        $data = array(
            'channel_sku_id' => $event->channelSkuId,
            'quantity_old' => empty($event->oldQuantity) ? 0 : $event->oldQuantity,
            'quantity_new' => $reservedQuantity->quantity,
            'order_id' => $event->orderId,
            'order_status' => $event->orderStatus,
            'item_id' => $event->itemId,
            'item_status' => $event->itemStatus
        );
        ReservedQuantityLog::create($data);
    }
}
