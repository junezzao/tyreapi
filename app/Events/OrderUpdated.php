<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderUpdated extends Event
{
    use SerializesModels;

    public $orderId;

    public $description;

    public $eventType;

    public $refType;

    public $refId;

    public $userId;

    public $eventInfo;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($orderId, $eventType, $refType, $refId, $eventInfo, $userId = 'N/A')
    {
        $this->orderId = $orderId;
        $this->eventType = $eventType;
        $this->refType = $refType;
        $this->refId = $refId;
        $this->userId = $userId;
        $this->eventInfo = $eventInfo;
        //Event::fire(new OrderUpdated($orderId, $eventType, $refType, $refId, $userId, $eventInfo));
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
