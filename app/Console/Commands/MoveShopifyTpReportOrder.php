<?php

namespace App\Console\Commands;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ThirdPartyReport;

define("DISCARDED_CUTOFF_DATE_IN_MOVE", "2016-12-01");//2016-10-01
class MoveShopifyTpReportOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // sample : php artisan command:moveShopifyTpReportOrder
    protected $signature = 'command:moveShopifyTpReportOrder 
                                {--start_date= : Start date in format Y-m-d}
                                {--end_date= : End date in format Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set all shopify orders for previous month to be under (Paid by Marketplace)';

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
        //now is the first day in the month then subDay will become the end day of the previous month
        //$previousMonth = Carbon::now()->subDay()->endOfDay();
        //$previousMonthWithoutTime = $previousMonth->toDateString();//"2017-02-28" 
        //$previousMonthWithTime = $previousMonth->toDateTimeString();//"2017-02-28 23:59:59" 
        if (!is_null($this->option('start_date'))) {
            $startDateTime = Carbon::createFromFormat('Y-m-d', $this->option('start_date'), 'Asia/Kuala_Lumpur')->startOfDay()->setTimezone('UTC');
        }
        else {
            $startDateTime = Carbon::now('Asia/Kuala_Lumpur')->subMonthNoOverflow()->startOfMonth()->setTimezone('UTC');
        }

        if (!is_null($this->option('end_date'))) {
            $endDateTime = Carbon::createFromFormat('Y-m-d', $this->option('end_date'), 'Asia/Kuala_Lumpur')->endOfDay()->setTimezone('UTC');
        }
        else {
            $endDateTime = Carbon::now('Asia/Kuala_Lumpur')->subMonthNoOverflow()->endOfMonth()->setTimezone('UTC');
        }
        $this->info('Moving Shopify orders from '.$startDateTime.' till '.$endDateTime);
        \Log::info('Moving Shopify orders from '.$startDateTime.' till '.$endDateTime);
        $shopifyId = ChannelType::whereIn('name', ['Shopify', 'Shopify POS'])->get(['id']);
        $tpOrderIds = ThirdPartyReport::select(\DB::raw('distinct order_id'))
                                ->whereNotNull('order_id')
                                ->pluck('order_id');
        $orders = Order::select('orders.id', 'orders.channel_id', 'orders.shipped_date', 'channels.channel_type_id')
                        ->join('channels', 'channels.id', '=', 'orders.channel_id')
                        ->whereIn('channels.channel_type_id', $shopifyId)
                        ->whereNotIn('orders.id', $tpOrderIds)
                        ->whereRaw('orders.shipped_date >= "'.DISCARDED_CUTOFF_DATE_IN_MOVE.'" AND orders.shipped_date >= "'.$startDateTime.'"')
                        ->where('orders.shipped_date','<=', $endDateTime)
                        //->select(\DB::raw('count(*) as total'))
                        ->get();
        $orders = json_decode($orders, true);
        foreach ($orders as $order) {
            $this->info('Order '.$order['id'].' - Shipped Date: '.$order['shipped_date']);
            \Log::info('Order '.$order['id'].' - Shipped Date: '.$order['shipped_date']);
            $orderItems = json_decode(OrderItem::where('order_id', '=', $order['id'])->get(), true);
            foreach ($orderItems as $orderItem) {
                $data =  array(
                    "media_id"                      => null,
                    "channel_type_id"               => $order['channel_type_id'],
                    "tp_order_code"                 => $orderItem['order']['tp_order_code'],
                    "order_id"                      => $orderItem['order']['id'],
                    "tp_item_id"                    => empty($orderItem['tp_item_id'])? $orderItem['id'] : $orderItem['tp_item_id'],
                    "order_item_id"                 => $orderItem['id'],
                    "hubwire_sku"                   => $orderItem['ref']['sku']['hubwire_sku'],
                    "product_id"                    => $orderItem['ref']['sku']['product_id'],
                    "quantity"                      => $orderItem['quantity'],
                    "item_status"                   => $orderItem['status'],
                    "unit_price"                    => $orderItem['unit_price'],
                    "sale_price"                    => $orderItem['sale_price'],
                    "sold_price"                    => $orderItem['sold_price'],
                    "channel_fees"                  => $orderItem['channel_fee'],
                    //"channel_shipping_fees"         => $orderItem['merchant_shipping_fee'],
                    "channel_payment_gateway_fees"  => $orderItem['channel_payment_gateway_fees'],
                    "net_payout"                    => floatval($orderItem['sale_price']-$orderItem['channel_fee']-$orderItem['merchant_shipping_fee']-$orderItem['channel_payment_gateway_fees']),
                    "net_payout_currency"           => $orderItem['order']['currency'],
                    "paid_status"                   => 1,
                    "payment_date"                  => Carbon::now()->setTimezone('Asia/Kuala_Lumpur')->toDateString(),//$previousMonthWithoutTime,
                    "tp_payout_ref"                 => null,
                    "status"                        => "Verified",
                    "merchant_payout_amount"        => "0.00",
                    "merchant_payout_currency"      => null,
                    "merchant_payout_status"        => null,
                    "hw_payout_bank"                => null,
                    "merchant_payout_date"          => null,
                    "merchant_payout_ref"           => null,
                    "merchant_bank"                 => null,
                    "merchant_payout_method"        => null,
                    "merchant_invoice_no"           => null,
                    "created_by"                    => '0',
                    "last_attended_by"              => null,
                    );
                ThirdPartyReport::create($data);
            }
        }
    }
}