<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\Admin\ChannelSKU;

class ChannelSkuQuantityChange extends Event
{
    use SerializesModels;

    public $channelSkuId;
    public $quantity;
    public $refTable;
    public $refTableId;
    public $action;

    /**
     * Create a new event instance.
     *
     * @param $channelSkuId
     * @param $quantity
     * @param $refTable
     * @param $refTableId
     * @param $action
     * 
     * @return void
     */
    public function __construct($channelSkuId, $quantity, $refTable, $refTableId, $action)
    {
        $this->channelSkuId = $channelSkuId;
        $this->quantity     = $quantity;
        $this->refTable     = $refTable;
        $this->refTableId   = $refTableId;
        $this->action       = $action;
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
