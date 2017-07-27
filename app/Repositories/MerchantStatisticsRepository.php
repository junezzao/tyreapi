<?php namespace App\Repositories;

use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exception\HttpResponseException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use App\Models\Admin\Merchant;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelSKU;
use Carbon\Carbon;


class MerchantStatisticsRepository
{
	protected $order;
	protected $merchantId;

    public function __construct($merchantId = null)
    {
    	$this->merchantId = $merchantId;
    }

    public function getStats($dateRange) 
    {
        // check if merchant exists
        $merchant = Merchant::find($this->merchantId);
        if (is_null($merchant) || empty($merchant)) {
            $data['code'] = 400;
            $data['error'][] = ['merchant_id' => 'Merchant does not exist'];
            return $data;
        }
        $data['code']                                     = 200;
    	$data['channels_performance']                     = $this->channelPerformance($dateRange);
    	$data['total_items_and_value_of_items_sold']      = $this->totalAndValueOfItemsSold($dateRange);
        $data['items_and_value_sold_per_channel']         = $this->itemsAndValueSoldPerChannel($dateRange);

        return $data;
    }

    //
    public function channelPerformance($dateRange) {
    	return \DB::table('order_items')
                    ->select(\DB::raw('channels.name, count(*) as count'))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('order_items.created_at', $dateRange)
                    ->where('order_items.merchant_id', '=', $this->merchantId)
                    ->where('channels.name', 'not like', '%Development Client%')
                    ->where('orders.status','=', Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy(\DB::raw('channels.name'))
                    ->orderBy('count', 'desc')
                    ->get();
    }

	// the total number of items sold and total value of items sold,
    public function totalAndValueOfItemsSold($dateRange) {
    	return \DB::table('order_items')
                    ->select(\DB::raw('DATE(order_items.created_at) as date, count(*) as count, sum(sold_price) as sum'))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('order_items.created_at', $dateRange)
                    ->where('order_items.merchant_id', '=', $this->merchantId)
                    ->where('channels.name', 'not like', '%Development Client%')
                    ->where('orders.status','=', Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy(\DB::raw('DATE(order_items.created_at)'))
                    ->orderBy('date', 'asc')
                    ->get();
    }

	// total items sold per channel, total value sold per channel, 
	public function itemsAndValueSoldPerChannel($dateRange) {
		$items = \DB::table('order_items')
                    ->select(\DB::raw('DATE(order_items.created_at) as date, channels.name, count(*) as count, sum(sold_price) as sum'))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('order_items.created_at', $dateRange)
                    ->where('order_items.merchant_id', '=', $this->merchantId)
                    ->where('channels.name', 'not like', '%Development Client%')
                    ->where('orders.status','=', Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy(\DB::raw('channels.name'))
                    ->groupBy(\DB::raw('DATE(order_items.created_at)'))
                    ->orderBy('date', 'asc')
                    ->get();

        return $this->groupByChannelName($items);
	}

    public function groupByChannelName($items) {
        $data = array();
        foreach ($items as $item) {
            // $item->name is channel name
            $data[$item->name][] = [
                'date' => $item->date,
                'sum'  => $item->sum,
                'count' => $item->count,
            ];
        }
        return $data;
    }
}
