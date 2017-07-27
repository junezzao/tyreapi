<?php

namespace App\Events;

use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\DeliveryOrderItem;
use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class StockTransferReceived extends Event
{
    use SerializesModels;
    public $stockTransfer;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($stockTransfer)
    {
        //
        $this->stockTransfer = $stockTransfer;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
