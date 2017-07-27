<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReservedQuantityChange extends Event
{
    use SerializesModels;

    public $channelSkuId;
    public $oldQuantity;
    public $orderId;
    public $orderStatus;
    public $itemId;
    public $itemStatus;

    /**
     * Create a new event instance.
     *
     * @param $channelSkuId
     * @param $oldQuantity
     * @param $refTable
     * @param $refTableId
     * 
     * @return void
     */
    public function __construct($channelSkuId, $oldQuantity, $orderId, $orderStatus, $itemId, $itemStatus)
    {
        $this->channelSkuId = $channelSkuId;
        $this->oldQuantity = $oldQuantity;
        $this->orderId = $orderId;
        $this->orderStatus = $orderStatus;
        $this->itemId = $itemId;
        $this->itemStatus = $itemStatus;
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
