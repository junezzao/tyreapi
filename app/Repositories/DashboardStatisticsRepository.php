<?php namespace App\Repositories;

use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exception\HttpResponseException;
use Activity;
use Helper;
use App\Models\Admin\Merchant;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\User;
use Carbon\Carbon;

class DashboardStatisticsRepository
{
	protected $order;
    protected $fromDate7Days;
    protected $fromDate30Days;
    protected $fromDate3Months;
    protected $currentTime;
    protected $today;
    protected $tomorrow;

    public function __construct()
    {
        $this->filterByMerchant = false;
        $this->merchant_id = null;
        $this->fromDate7Days = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::today()->toDateTimeString())->subDays(6);
        $this->fromDate30Days = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::today()->toDateTimeString())->subDays(30);
        $this->fromDate3Months = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::today()->toDateTimeString())->subMonths(3);
        $this->currentTime = date('Y-m-d H:i:s');
        $adminTz = config('globals.hubwire_default_timezone');
        $this->today = Helper::convertTimeToUTC(Carbon::today($adminTz), $adminTz);
        $this->tomorrow = Helper::convertTimeToUTC(Carbon::tomorrow($adminTz), $adminTz);
    } 

    public function generateStats()
    {
        $this->generateStatsWeekly();
        $this->generateStatsMonthly();
        $this->generateStatsTrimonthly();
    }

    public function generateStatsWeekly()
    {
        $frequency = 'Weekly';
        
        $stats = $this->orderStats($this->fromDate7Days);
        $stats['new_signups'] = Merchant::whereBetween('created_at', [date("Y-m-01"), date("Y-m-d")])->get()->count();
        $stats['active_merchants'] = $this->counterStats($this->fromDate7Days);
        $stats['gmv'] = $this->gmv($this->fromDate7Days);
        $stats['merchants_signed'] = $this->merchantStats($this->fromDate7Days);
        $stats['successful_orders_count'] = $this->completedOrders($this->fromDate7Days);

        // store stats
        $this->insertStatsIntoDB($stats, $frequency);
        
        return $stats;
    }

    public function generateStatsMonthly()
    {   
        $frequency = 'Monthly';

        $stats = $this->orderStats($this->fromDate30Days);
        $stats['total_merchants'] = Merchant::all()->count();
        $stats['active_merchants'] = $this->counterStats($this->fromDate30Days);
        $stats['gmv'] = $this->gmv($this->fromDate30Days);
        $stats['merchants_signed'] = $this->merchantStats($this->fromDate30Days);
        $stats['successful_orders_count'] = $this->completedOrders($this->fromDate30Days);
        
        // store stats
        $this->insertStatsIntoDB($stats, $frequency);

        return $stats;
    }

    public function generateStatsTrimonthly()
    {
        $frequency = 'Trimonthly';

        $stats['successful_orders_count'] = $this->completedOrders($this->fromDate3Months);

        // store stats
        $this->insertStatsIntoDB($stats, $frequency);

        return $stats;
    }

    // Total active merchants 7 days/30 days 
    public function counterStats($date)
    {
        $query = "SELECT count(*) as active_merchants from 
                    (select count(merchants.id) 
                        from merchants 
                        left join order_items on merchants.id = order_items.merchant_id 
                        left join orders on order_items.order_id = orders.id
                        where orders.updated_at >= 'FROM_DATE' 
                        and orders.updated_at < date('Y-m-d') 
                        and merchants.deleted_at IS NULL group by merchants.id
                    ) as total";

        $stats = \DB::select(\DB::raw(str_replace('FROM_DATE', $date, $query)))[0]->active_merchants;

        return $stats;
    }

    // count of successful orders in one day
    public function completedOrders($date)
    {
        return $this->groupByChannelTypeId(
                    \DB::table('orders')->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                        ->select(\DB::raw('channels.channel_type_id, DATE(orders.created_at) as date, count(*) as count'))
                        ->where('channels.name', 'not like', '%Development Client%')
                        ->where('channels.channel_type_id', '<>', 0)
                        ->where('orders.status', '=', Order::$completedStatus)
                        ->where('orders.created_at', '>=', $date)
                        ->where('orders.created_at', '<', date("Y-m-d"))
                        ->groupBy(\DB::raw('DATE(orders.created_at)'))
                        ->groupBy('channels.channel_type_id')
                        ->get()
                );
    }

    // daily sales orders and orders items widget
    public function orderStats($date)
    {
        $stats = array();

        // number of order items
        // not pending or failed
        $stats['order_items_count'] = $this->groupByChannelTypeId(
                            \DB::table('order_items')->select(\DB::raw('channels.channel_type_id, DATE(order_items.created_at) as date, count(*) as count'))
                                ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                                ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')              
                                ->where('order_items.created_at', '>=', $date)
                                ->where('order_items.created_at', '<', date("Y-m-d"))
                                ->where('channels.name', 'not like', '%Development Client%')
                                ->where('channels.channel_type_id', '<>', 0)
                                ->where('orders.status', '=', Order::$completedStatus)
                                ->whereIn('order_items.status', ['Verified', 'Returned'])
                                ->groupBy(\DB::raw('DATE(order_items.created_at)'))
                                ->groupBy('channels.channel_type_id')
                                ->get()
                        );

        // number of order items
        $stats['returned_order_items_count'] = $this->groupByChannelTypeId(
                                    \DB::table('order_items')->select(\DB::raw('channels.channel_type_id, DATE(order_items.created_at) as date, count(*) as count'))
                                        ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                        ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id') 
                                        ->where('order_items.created_at', '>=', $date)
                                        ->where('order_items.created_at', '<', date("Y-m-d"))
                                        ->where('channels.name', 'not like', '%Development Client%')
                                        ->where('channels.channel_type_id', '<>', 0)
                                        ->where(function ($query) {
                                            $query->where('order_items.status', '=', 'Cancelled')
                                                  ->orWhere('order_items.status', '=', 'Returned');
                                        })
                                        ->groupBy(\DB::raw('DATE(order_items.created_at)'))
                                        ->groupBy('channels.channel_type_id')
                                        ->get()
                                );

        // verified, confirmed sales
        // number of orders items by channel type
        $stats['order_items_channel_type_count'] = $this->groupByChannelTypeId(
                                                    \DB::table('order_items')->select(\DB::raw('channel_types.name, channels.channel_type_id, count(*) as count'))
                                                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                                                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                                                    ->leftjoin('channel_types', 'channel_types.id','=', 'channels.channel_type_id')
                                                    ->where('channels.name', 'not like', '%Development Client%')
                                                    ->where('orders.status','=',Order::$completedStatus)
                                                    ->where('order_items.status', '=', 'Verified')
                                                    ->where('order_items.created_at', '>=', $date)
                                                    ->where('order_items.created_at', '<', date("Y-m-d"))
                                                    ->groupBy('channel_type_id')
                                                    ->orderBy('count', 'desc')
                                                    ->get()
                                                );
        
        // top 3 performing channels
        $stats['order_items_channels_count'] = $this->groupByChannelTypeId(
                                                        \DB::table('order_items')
                                                        ->select(\DB::raw('channels.name, channels.channel_type_id, count(*) as count'))
                                                        ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                                                        ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                                        ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                                                        ->where('order_items.created_at', '>=', $date)
                                                        ->where('order_items.created_at', '<', date("Y-m-d"))
                                                        ->where('channels.name', 'not like', '%Development Client%')
                                                        ->where('orders.status','=',Order::$completedStatus)
                                                        ->where('order_items.status', '=', 'Verified')
                                                        ->groupBy(\DB::raw('channels.name'))
                                                        ->groupBy('channels.channel_type_id')
                                                        ->orderBy('count', 'desc')
                                                        ->get()
                                                );

        return $stats;
    }

    // gross merchandise value
    public function gmv($date) 
    {
        $stats = OrderItem::select(\DB::raw('channels.channel_type_id, DATE(order_items.created_at) as date, sum(sold_price) as count'))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->where('order_items.created_at', '>=', $date)
                    ->where('order_items.created_at', '<', date("Y-m-d"))
                    ->where('channels.name', 'not like', '%Development Client%')
                    ->where('channels.channel_type_id', '<>', 0)
                    ->where('orders.status','=',Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy(\DB::raw('DATE(order_items.created_at)'))
                    ->groupBy('channels.channel_type_id')
                    ->get();

        return $this->groupByChannelTypeId($stats);
    }

    public function merchantStats($date)
    {
        $stats = Merchant::select(\DB::raw('DATE(created_at) as date, count(*) as count'))
                    ->groupBy(\DB::raw('DATE(created_at)'))
                    ->whereBetween('created_at', [$date, date("Y-m-d")])
                    ->get();

        return $stats;
    }

    // Group stats by channel type id
    public function groupByChannelTypeId($data) {
        $stats = array();
        foreach ($data as $d) {
            if (isset($d->channel_type_id)) {
                $stats[$d->channel_type_id][] = $d;
                unset($d->channel_type_id);
            }
        }
        return $stats;
    }

    public function insertStatsIntoDB($stats, $frequency) {
        foreach ($stats as $key=>$val) {
            \DB::table('dashboard_stats')->insert(
                    [ 'frequency'=>$frequency, 
                      'title'=>$key, 
                      'data'=>json_encode($val), 
                      'created_at'=>$this->currentTime, 
                      'updated_at'=>$this->currentTime
                    ]
                );
        }
    }

    // for dashboard live counters
    public function countOrdersAndOrderItems() {
        $stats = \DB::table('order_items')->select(\DB::raw('count(distinct order_items.order_id) as orders_count, count(order_items.id) as order_items_count'))
                                            ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                            ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id') 
                                            ->where('order_items.created_at', '>=', $this->today)
                                            ->where('order_items.created_at', '<', $this->tomorrow)
                                            ->where('channels.name', 'not like', '%Development Client%')
                                            ->where('channels.channel_type_id', '<>', 0);
        if($this->filterByMerchant) 
        $stats = $stats -> where('order_items.merchant_id',$this->merchant_id);

        $stats = $stats->get();

        $data['order_items_count'] = isset($stats[0]->order_items_count) ? $stats[0]->order_items_count : 0;
        $data['orders_count'] = isset($stats[0]->orders_count) ? $stats[0]->orders_count : 0;
        return $data;
    }

    public function countCancelledItems() {
        $stats = \DB::table('order_items')->select(\DB::raw('count(*) as count'))
                                        ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                        ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id') 
                                        ->where('order_items.updated_at', '>=', $this->today)
                                        ->where('order_items.updated_at', '<', $this->tomorrow)
                                        ->where('channels.name', 'not like', '%Development Client%')
                                        ->where('channels.channel_type_id', '<>', 0)
                                        ->where('order_items.status', '=', 'Cancelled');
        if($this->filterByMerchant) 
        $stats = $stats -> where('order_items.merchant_id',$this->merchant_id);

        $stats = $stats->get();
        
        $data['cancelled_count'] = isset($stats[0]->count) ? $stats[0]->count : 0;
        return $data;
    }

    public function countReturnedItems() {
        $stats = \DB::table('order_items')->select(\DB::raw('count(*) as count'))
                                        ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                        ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id') 
                                        ->where('order_items.updated_at', '>=', $this->today)
                                        ->where('order_items.updated_at', '<', $this->tomorrow)
                                        ->where('channels.name', 'not like', '%Development Client%')
                                        ->where('channels.channel_type_id', '<>', 0)
                                        ->where('order_items.status', '=', 'Returned');
        
        if($this->filterByMerchant) 
        $stats = $stats -> where('order_items.merchant_id',$this->merchant_id);

        $stats = $stats->get();

        $data['returned_count'] = isset($stats[0]->count) ? $stats[0]->count : 0;
        return $data;
    }

    public function countReturnedTransitItems() {
        $stats = \DB::table('order_items')->select(\DB::raw('count(*) as count'))
                                        ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                        ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id') 
                                        ->leftjoin('return_log', 'return_log.order_item_id', '=', 'order_items.id') 
                                        ->where('order_items.updated_at', '>=', $this->today)
                                        ->where('order_items.updated_at', '<', $this->tomorrow)
                                        ->where('channels.name', 'not like', '%Development Client%')
                                        ->where('channels.channel_type_id', '<>', 0)
                                        ->where('return_log.status', '=', 'In Transit');
                                        
        
        if($this->filterByMerchant) 
        $stats = $stats -> where('order_items.merchant_id',$this->merchant_id);

        $stats = $stats->get();

        $data['transit_count'] = isset($stats[0]->count) ? $stats[0]->count : 0;
        return $data;
    }

    public function countOutOfStockSKUs() {
        $stats = \DB::table('channel_sku')->select(\DB::raw('sum(channel_sku_quantity) as quantity,sku.hubwire_sku,products.name as product, products.merchant_id'))
                                        ->leftjoin('sku','sku.sku_id', '=', 'channel_sku.sku_id')
                                        ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                                        ->whereRaw('products.deleted_at IS NULL')
                                        ->whereRaw('channel_sku.deleted_at IS NULL');
                                        
        if($this->filterByMerchant) 
        $stats = $stats -> where('products.merchant_id',$this->merchant_id);

        $stats = $stats->groupBy('channel_sku.sku_id') ->havingRaw('quantity = 0')->get();

        $data['out_of_stocks'] = $stats;
        return $data;
    }

    public function byMerchant($merchant_id)
    {
        $this->merchant_id = $merchant_id;
        $this->filterByMerchant =  true;
        return $this;
    }
}
