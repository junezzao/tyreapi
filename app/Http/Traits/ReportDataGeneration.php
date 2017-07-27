<?php namespace App\Http\Traits;

use App\Models\Admin\Order;
use App\Models\Admin\Merchant;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use App\Models\Admin\IssuingCompany;
use App\Models\Admin\ReturnLog;
use Carbon\Carbon;

trait ReportDataGeneration
{
    private $warehouse_channel_type = 12;
    private $test_merchant = "Test Merchant";
    private $test_channels = "%[Development Client]%";
    private $correction_channels = "%Correction%";

    private function getSalesReportData($dateRange, $merchantsFilter = [], $channelsFilter = [], $paginate = false)
    {
        // get all sales for the duration
        ini_set('memory_limit','-1');
        ini_set('max_execution_time', 600);

        $query = Order::leftJoin(
        \DB::raw("
            (select
                `order_status_log`.`order_id`,
                `order_status_log`.`to_status`,
                `order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0
            ) `order_completed`
        "), 'order_completed.order_id', '=', 'orders.id');
        if(!empty($channelsFilter)) {
            $query->whereIn('orders.channel_id', $channelsFilter);
        }
        $query->where('order_completed.completed_date', '>=', $dateRange[0]);
        $query->where('order_completed.completed_date', '<=', $dateRange[1]);
        //$query->whereBetween('order_completed.completed_date', $dateRange);
        $query->where('status', Order::$completedStatus);

        $sales = $query->get();

        if(!empty($merchantsFilter)){
            $merchants = Merchant::select('id', 'name', 'slug')->whereIn('id', $merchantsFilter)->get()->keyBy('id')->toArray();
        }else{
            $merchants = Merchant::select('id', 'name', 'slug')->get()->keyBy('id')->toArray();
        }

        $saleMasterData = array();
        $totalOrders = 0;
        $totalOrderItems = 0;
        $totalOrdersValue = 0;
        $totalOrderItemsValue = 0;
        $totalOrderShippingFee = 0;

        foreach ($sales as $sale) {

            $tempOrderItems =0;
            $items = $sale->itemSKUs;
            $itemArr = array();

            foreach ($items as $item) {

                if( empty($merchantsFilter) || (!empty($merchantsFilter) && in_array($item->merchant_id, $merchantsFilter)) ){
                    if ($item->isChargeable()) {

                        if($item->tax_inclusive == true) {
                            $soldAmount = $item->sold_price;
                            $soldAmountWithoutGst = $item->sold_price - $item->tax;
                        } else {
                            $soldAmount = $item->sold_price + $item->tax;
                            $soldAmountWithoutGst = $item->sold_price;
                        }

                        $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                        $channel_sku = ChannelSKU::find($item->ref_id);
                        $channel = Channel::with('channel_type')->find($sale->channel_id);
                        $sku = $item->ref->sku;
                        $product = Product::with('brand', 'merchant', 'category')->find($sku->product_id);
                        $issuing_company = IssuingCompany::where('id', $channel->issuing_company)->first();
                        $gst_reg = $issuing_company->gst_reg;
                        $tax_rate = $item->tax_rate;
                        $item_sale_price = ($item->sale_price == 0 ? $item->unit_price:$item->sale_price);
                        if(strcmp($product->merchant->name,$this->test_merchant)==0){
                            continue;
                        }
                        $tempOrderItems+=1;
                        $totalOrderItems+=1;
                        $totalOrderItemsValue = $totalOrderItemsValue + $item_sale_price;
                        $totalOrderShippingFee += $item->merchant_shipping_fee;
                        $category = '';
                        if (isset($product->category) && !empty($product->category->full_name)) {
                            $category = explode('/', $product->category->full_name);
                        }
                        $reportData = [
                            'Merchant' => $product->merchant->name,
                            'Channel' => $channel->name,
                            'Channel Type' => $channel->channel_type->name,
                            'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $sale->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y'),
                            'Order Completed Date' => !($sale->orderDate($sale->id))?$sale->orderDate($sale->id):Carbon::createFromFormat('Y-m-d H:i:s', $sale->orderDate($sale->id))->format('d/m/Y H:i:s'),
                            'Order No' => $sale->id,
                            'Third Party Order No' => $sale->tp_order_code,
                            'Brand' => $product->getRelation('brand')->name,
                            'Hubwire SKU' => $sku->hubwire_sku,
                            'Supplier SKU' => $sku->sku_supplier_code,
                            'Product Name' => $product->name,
                            'Product Category' => isset($product->category->full_name)? $product->category->full_name : '',
                            'Main Category' => isset($category[0])? $category[0] : '',
                            'Sub-category' => isset($category[1])? $category[1] : '',
                            'Sub-subcategory' => isset($category[2])? $category[2] : '',
                            'Size' => $sku->size,
                            'Color' => $sku->color,
                            'Quantity' => $item->original_quantity,
                            'Currency' => $sale->currency,//$channel->issuing_company,//
                            'Retail Price (Incl. GST)' => ($gst_reg == 1)?number_format($item->unit_price, 2): number_format($item->unit_price/(1+$tax_rate), 2),
                            'Retail Price (Excl. GST)' => number_format($item->unit_price/(1+$tax_rate), 2),
                            'Listing Price (Incl. GST)'=> ($gst_reg == 1)?number_format($item_sale_price, 2): number_format($item_sale_price/(1+$tax_rate), 2),
                            'Listing Price (Excl. GST)'=> number_format($item_sale_price/(1+$tax_rate), 2),
                            'Discounts' => number_format($discount * $item->original_quantity, 2), // sum of all the quantities
                            'Total Sales (Incl. GST)' => ($gst_reg == 1)?number_format($item->sold_price * $item->original_quantity, 2): number_format($item->sold_price * $item->original_quantity, 2),
                            'Total Sales (Excl. GST)' => ($gst_reg == 1)?number_format($soldAmountWithoutGst * $item->original_quantity, 2): number_format($item->sold_price * $item->original_quantity, 2),
                            'Consignment Number' => $item->order->consignment_no,
                            'Arc Shipping Fee (Incl. GST)' => number_format($item->merchant_shipping_fee, 2),
                        ];

                        $saleMasterData[] = $reportData;

                        if (array_key_exists($product->merchant_id, $merchants)) {
                            $merchants[$product->merchant_id]['sales'][] = $reportData;
                        }
                    }
                }


            }
            if($tempOrderItems>0){
                $totalOrders+=1;
                $totalOrdersValue = $totalOrdersValue + $sale->subtotal;
            }
        }

        return [    'saleMasterData'        => $saleMasterData,
                    'merchantData'          => $merchants,
                    'totalOrders'           => $totalOrders,
                    'totalOrderItems'       => $totalOrderItems,
                    'totalOrdersValue'      => $totalOrdersValue,
                    'totalOrderItemsValue'  => $totalOrderItemsValue,
                    'totalOrderShippingFee' => $totalOrderShippingFee,
                    'dateRange'             => $dateRange
                ];
    }

    // merchant reports overview page - when selecting > 1 merchants
    public function merchantPerformanceOverview($dateRange, $merchants) {
        $merchantFilter = !empty($merchants)? ' AND order_items.merchant_id in ('.implode(", ", $merchants).') ': '';
        $skus = \DB::table('channel_sku')
                    ->select(\DB::raw('merchants.id as merchant_id,
                                        merchants.name,
                                        COALESCE( sum(
                                            (CASE WHEN quantity_log_app.quantity_new IS NOT NULL
                                            THEN quantity_log_app.quantity_new
                                            ELSE channel_sku.channel_sku_quantity
                                            END)
                                        ) + SUM(order_items.qty_in_warehouse), 0) as quantity_in_hand,
                                        COALESCE ( sum(
                                            (CASE WHEN quantity_log_app.quantity_new IS NOT NULL
                                            THEN quantity_log_app.quantity_new
                                            ELSE channel_sku.channel_sku_quantity
                                            END)
                                        ), 0 ) as available_stock,
                                        COALESCE( SUM(order_items.sold), 0) as sold,
                                        COALESCE( SUM(order_items.gmv), 0) as gmv,
                                        COALESCE( SUM(order_items.return_in_transit), 0) as return_in_transit,
                                        COALESCE( SUM(order_items.return_complete), 0) as return_complete,
                                        COALESCE( SUM(order_items.cancelled), 0) as cancelled
                                     '))
                    ->leftjoin(\DB::raw("(SELECT sum(order_items.original_quantity) as sold,
                                            SUM(CASE WHEN
                                                order_items.status <> \"Cancelled\"
                                                AND order_items.status <> \"Returned\"
                                                THEN (CASE order_items.sale_price WHEN 0
                                                        THEN order_items.unit_price
                                                        ELSE order_items.sale_price
                                                    END)
                                                ELSE
                                                    0
                                                END
                                            ) as gmv,
                                            sum(case when order_items.reserved = 1 then 1 else 0 end) as qty_in_warehouse,
                                            count(case order_items.status when \"Cancelled\" then 1 else null end) as cancelled,
                                            count(case when order_items.status = \"Returned\" and return_log.status = \"In Transit\" then 1 else null end) as return_in_transit,
                                            count(case when order_items.status = \"Returned\" and return_log.status = \"Restocked\" then 1 else null end) as return_complete,
                                            order_items.ref_id
                                            FROM order_items
                                            LEFT JOIN return_log on return_log.order_item_id = order_items.id
                                            LEFT JOIN orders on orders.id = order_items.order_id
                                            WHERE orders.shipped_date BETWEEN \"$dateRange[0]\" and \"$dateRange[1]\"
                                            and (order_items.status = \"Verified\" or order_items.status = \"Returned\")
                                            $merchantFilter
                                            GROUP BY order_items.ref_id
                                        ) as order_items"), 'order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                    ->leftjoin(\DB::raw("(SELECT quantity_log_app.channel_sku_id,
                                                quantity_log_app.quantity_new
                                            FROM quantity_log_app
                                            WHERE quantity_log_app.created_at < \"$dateRange[1]\"
                                            ORDER BY created_at DESC
                                            LIMIT 1
                                        ) as quantity_log_app"), 'quantity_log_app.channel_sku_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->leftjoin('merchants', 'merchants.id', '=', 'sku.merchant_id')
                    ->where('channels.name', 'not like', $this->test_merchant)
                    ;

        if (!empty($merchants))
            $skus = $skus->whereIn('merchants.id', $merchants);

        $skus = $skus->groupBy('sku.merchant_id')
                    ->orderBy('sold', 'desc')
                    ->get();

        return $skus;
    }

    /*
        summary to show:
        2. No of products out of stock
        3. the total number of items sold
        4. total value of items sold
        5. Best performing channel
        6. Best Performing channel (value)
    */
    public function merchantPerformanceBreakdownSummary($merchantId, $dateRange)
    {
        // 3 & 4
        $totalAndValueOfItemsSold = \DB::table('order_items')
                    ->select(\DB::raw('
                        count(*) as count,
                        sum((CASE order_items.sale_price WHEN 0
                            THEN order_items.unit_price
                            ELSE order_items.sale_price
                        END)) as sum'
                    ))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('orders.shipped_date', $dateRange)
                    ->where('order_items.merchant_id', '=', $merchantId)
                    ->where('channels.name', 'not like', $this->test_channels)
                    ->where('orders.status','=', Order::$completedStatus)
                    ->whereIn('order_items.status', ['Verified', 'Returned'])
                    ->get();

        $channelPerformance = $this->bestPerformingChannel($merchantId, $dateRange);

        $outOfStock = \DB::table('products')
                    ->select(\DB::raw('sum((CASE WHEN quantity_log_app.quantity_new IS NOT NULL
                                            THEN quantity_log_app.quantity_new
                                            ELSE channel_sku.channel_sku_quantity
                                        END)) as quantity')
                            )
                    ->leftjoin('sku', 'sku.product_id', '=', 'products.id')
                    ->leftjoin('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                    ->leftjoin(\DB::raw("(SELECT quantity_log_app.channel_sku_id,
                                                quantity_log_app.quantity_new
                                            FROM quantity_log_app
                                            WHERE quantity_log_app.created_at < \"$dateRange[1]\"
                                            ORDER BY created_at DESC
                                            LIMIT 1
                                        ) as quantity_log_app"), 'quantity_log_app.channel_sku_id', '=', 'channel_sku.channel_sku_id')
                    ->where('products.merchant_id', '=', $merchantId)
                    ->groupBy('products.id')
                    ->havingRaw('quantity < 1 or quantity IS NULL')
                    ->get();

        $gmvAndItemsSoldPerChannel = \DB::table('order_items')
                    ->select(\DB::raw('
                        channels.name,
                        count(*) as count,
                        sum((CASE order_items.sale_price WHEN 0
                            THEN order_items.unit_price
                            ELSE order_items.sale_price
                        END)) as sum'
                    ))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('orders.shipped_date', $dateRange)
                    ->where('order_items.merchant_id', '=', $merchantId)
                    ->where('channels.name', 'not like', $this->test_channels)
                    ->where('orders.status','=', Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy('channels.name')
                    ->get();

        return array(
                    'total_num_and_value_items_sold' => $totalAndValueOfItemsSold,
                    'channel_performance' => $channelPerformance,
                    'out_of_stock_products' => count($outOfStock),
                    'num_and_value_items_sold_per_channel' => $gmvAndItemsSoldPerChannel,
                );
    }

    // merchant performance report table data - when selecting one merchant only
    public function merchantPerformanceBreakdown($merchantId, $dateRange) {
        $skus = \DB::table('channel_sku')
                    ->select(\DB::raw('sku.hubwire_sku,
                                        brands.name as brand_name,
                                        products.id,
                                        products.name as product_name,
                                        categories.full_name as category_name,
                                        COALESCE( sum(
                                            (CASE WHEN quantity_log_app.quantity_new IS NOT NULL
                                            THEN quantity_log_app.quantity_new
                                            ELSE channel_sku.channel_sku_quantity
                                            END)
                                        ) + SUM(order_items.qty_in_warehouse), 0) as quantity_in_hand,
                                        sum(
                                            (CASE WHEN quantity_log_app.quantity_new IS NOT NULL
                                            THEN quantity_log_app.quantity_new
                                            ELSE channel_sku.channel_sku_quantity
                                            END)
                                        ) as available_stock,
                                        COALESCE( SUM(order_items.sold), 0) as sold,
                                        COALESCE( SUM(order_items.gmv), 0) as gmv,
                                        COALESCE( SUM(order_items.return_in_transit), 0) as return_in_transit,
                                        COALESCE( SUM(order_items.return_complete), 0) as return_complete,
                                        COALESCE( SUM(order_items.cancelled), 0) as cancelled
                                     '))
                    ->leftjoin(\DB::raw("(SELECT sum(order_items.original_quantity) as sold,
                                            SUM(CASE WHEN
                                                order_items.status <> \"Cancelled\"
                                                AND order_items.status <> \"Returned\"
                                                THEN (CASE order_items.sale_price WHEN 0
                                                        THEN order_items.unit_price
                                                        ELSE order_items.sale_price
                                                    END)
                                                ELSE
                                                    0
                                                END
                                            ) as gmv,
                                            sum(case order_items.reserved when 1 then 1 else 0 end) as qty_in_warehouse,
                                            count(case order_items.status when \"Cancelled\" then 1 else null end) as cancelled,
                                            count(case when order_items.status = \"Returned\" and return_log.status = \"In Transit\" then 1 else null end) as return_in_transit,
                                            count(case when order_items.status = \"Returned\" and return_log.status = \"Restocked\" then 1 else null end) as return_complete,
                                            order_items.ref_id
                                            FROM order_items
                                            LEFT JOIN return_log on return_log.order_item_id = order_items.id
                                            LEFT JOIN orders on orders.id = order_items.order_id
                                            WHERE order_items.merchant_id = $merchantId
                                            and orders.shipped_date BETWEEN \"$dateRange[0]\" and \"$dateRange[1]\"
                                            and (order_items.status = \"Verified\" or order_items.status = \"Returned\")
                                            GROUP BY order_items.ref_id
                                        ) as order_items"), 'order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                    ->leftjoin(\DB::raw("(SELECT quantity_log_app.channel_sku_id,
                                                quantity_log_app.quantity_new
                                            FROM quantity_log_app
                                            WHERE quantity_log_app.created_at < \"$dateRange[1]\"
                                            ORDER BY created_at DESC
                                            LIMIT 1
                                        ) as quantity_log_app"), 'quantity_log_app.channel_sku_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                    ->leftjoin('categories', 'categories.id', '=', 'products.category_id')
                    ->leftjoin('brands', 'brands.id', '=', 'products.brand_id')
                    ->where('products.merchant_id', '=', $merchantId)
                    ->where('channels.name', 'not like', $this->test_channels)
                    ->groupBy('sku.sku_id')
                    ->orderBy('gmv', 'desc')
                    ->get();

        return $skus;
    }

    public function bestPerformingChannel($merchantId, $dateRange) {
        $mostItemsSold = \DB::table('order_items')
                            ->select(\DB::raw('channels.name, count(*) as count'))
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                            ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                            ->whereBetween('order_items.created_at', $dateRange)
                            ->where('order_items.merchant_id', '=', $merchantId)
                            ->where('channels.name', 'not like', $this->test_channels)
                            ->where('orders.status','=', Order::$completedStatus)
                            ->where('order_items.status', '=', 'Verified')
                            ->groupBy(\DB::raw('channels.name'))
                            ->orderBy('count', 'desc')
                            ->limit(1)
                            ->get();

        $mostValueSold = \DB::table('order_items')
                    ->select(\DB::raw('
                        channels.name, sum(
                        (CASE order_items.sale_price WHEN 0
                            THEN order_items.unit_price
                            ELSE order_items.sale_price
                        END)) as sum'))
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channel_sku','order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                    ->leftjoin('channels', 'channel_sku.channel_id', '=', 'channels.id')
                    ->whereBetween('order_items.created_at', $dateRange)
                    ->where('order_items.merchant_id', '=', $merchantId)
                    ->where('channels.name', 'not like', $this->test_channels)
                    ->where('orders.status','=', Order::$completedStatus)
                    ->where('order_items.status', '=', 'Verified')
                    ->groupBy(\DB::raw('channels.name'))
                    ->orderBy('sum', 'desc')
                    ->limit(1)
                    ->get();

        return array(
                    'most_items_sold' => $mostItemsSold,
                    'most_value_sold' => $mostValueSold
                );
    }

    private function getReturnsReportData($dateRange, $merchantsFilter = [], $channelsFilter = [], $paginate = false)
    {
        $returnsData = array();

        $newQuery = ReturnLog::whereBetween('created_at', $dateRange);
        if(!empty($channelsFilter)){
            $newQuery->whereHas('order', function($query) use($channelsFilter){
                $query->whereIn('channel_id', $channelsFilter);
            });
        }
        if(!empty($merchantsFilter)){
            $newQuery->whereHas('item', function($query) use($merchantsFilter){
                $query->whereIn('merchant_id', $merchantsFilter);
            });
        }
        $newReturns = $newQuery->get()->toArray();

        $pendingQuery = ReturnLog::where('created_at', '<', $dateRange[0])
                        ->where(function($query) use ($dateRange){
                            $query->whereNull('completed_at')->orWhere('completed_at', '>', $dateRange[1]);
                        });
        if(!empty($channelsFilter)){
            $pendingQuery->whereHas('order', function($query) use($channelsFilter){
                $query->whereIn('channel_id', $channelsFilter);
            });
        }
        if(!empty($merchantsFilter)){
            $pendingQuery->whereHas('item', function($query) use($merchantsFilter){
                $query->whereIn('merchant_id', $merchantsFilter);
            });
        }
        $pendingReturns = $pendingQuery->get()->toArray();

        $completedQuery = ReturnLog::whereBetween('completed_at', $dateRange);
        if(!empty($channelsFilter)){
            $completedQuery->whereHas('order', function($query) use($channelsFilter){
                $query->whereIn('channel_id', $channelsFilter);
            });
        }
        if(!empty($merchantsFilter)){
            $completedQuery->whereHas('item', function($query) use($merchantsFilter){
                $query->whereIn('merchant_id', $merchantsFilter);
            });
        }
        $completedReturns = $completedQuery->get()->toArray();
        $returnsData = array('new' => $newReturns, 'pending' => $pendingReturns, 'completed' => $completedReturns);

        return $returnsData;

    }
}