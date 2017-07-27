<?php

namespace App\Listeners;

use App\Events\StockTransferReceived;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogQuantityChange
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
     * @param  StockTransferReceived  $event
     * @return void
     */
    public function handle(StockTransferReceived $event)
    {
        //
        $skus = $event->stockTransfer;
        \DB::table('quantity_log_app')->insert($skus);
    }
}
