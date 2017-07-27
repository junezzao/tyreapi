<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Admin\Order;
use App\Models\Admin\OrderStatusLog;
use Event;
use App\Events\OrderUpdated;
use Log;

class UpdateOrderStatusToCompleted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:updateOrderStatusToCompleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $shhippedOrders = Order::where('status', Order::$shippedStatus)->where('cancelled_status', false)->where('paid_status', true)->get();
        
        Log::info('=======================');
        Log::info('Begin auto update status of orders from Shipped to Completed at '.Carbon::now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s Z'));
        
        foreach ($shhippedOrders as $order) {
            $order->status = Order::$completedStatus;
            $order->save();
            Log::info('Updated order '.$order->id);
            $shippedDate = $order->getStatusDate($order->id, 'Shipped');

            $statusLog = new OrderStatusLog(['user_id' => 0, 'from_status' => 'Shipped', 'to_status' => 'Completed', 'created_at' => $shippedDate]);
            $log = $order->statusLog()->save($statusLog);
            $this->info($order->id);
            
            // Fire 2 events, one for status updated, another for order completed
            $eventInfo = array(
                'fromStatus' => 31,
                'toStatus' => 32,
            );
            Event::fire(new OrderUpdated($order->id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, 0));
        }

        Log::info('End auto update status of orders from Shipped to Completed at '.Carbon::now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s Z'));
        Log::info('Total orders updated: '.count($shhippedOrders));
        Log::info('=======================');
    }
}
