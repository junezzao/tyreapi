<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Channel;
use Carbon\Carbon;

class CalculateShippingFee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // php artisan command:calculateShippingFee --date_from="2017-1-1" --date_until="2017-1-31"
    protected $signature = 'command:calculateShippingFee
                            {--date_from= : Calculate Shipping Fee from date in format Y-m-d}
                            {--date_until= : Calculate Shipping Fee until date in format Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate Shipping Fee for order_items';

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
        $date_from = $this->option('date_from');
        $date_until = $this->option('date_until');
        $dateFrom = (!is_null($date_from))? Carbon::createFromFormat('Y-m-d', $date_from)->startOfDay()->setTimezone('Asia/Kuala_Lumpur')->toDateTimeString() : Carbon::today()->setTimezone('Asia/Kuala_Lumpur')->startOfMonth()->toDateTimeString();
        $dateUntil = (!is_null($date_until))? Carbon::createFromFormat('Y-m-d', $date_until)->endOfDay()->setTimezone('Asia/Kuala_Lumpur')->toDateTimeString() : Carbon::today()->setTimezone('Asia/Kuala_Lumpur')->endOfMonth()->toDateTimeString();
        $dateRange = [$dateFrom, $dateUntil];
        $orders = Order::whereBetween('created_at', $dateRange)->get();
        foreach ($orders as $order) {
            $channel = $order->channel;
            $orderItems = OrderItem::where('order_id', '=', $order['id'])->groupBy('merchant_id')->get();
            foreach ($orderItems as $orderItem) {
                $getRate = Order::getShippingRateDetails($order, $channel, $orderItem['merchant_id']);
                if (!empty($getRate)) {
                    Order::calculateShippingFee($getRate, $order['id'], $orderItem['merchant_id'], true);
                }
            }
        }
    }
}
