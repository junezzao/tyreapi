<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class StockTransferCreated extends Event
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