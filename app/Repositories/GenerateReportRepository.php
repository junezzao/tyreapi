<?php namespace App\Repositories;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Admin\Order;
use App\Models\Admin\Merchant;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\Contract;
use App\Models\Admin\Fee;
use DB;
use Excel;
use Log;
use Storage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;



class GenerateReportRepository
{
	protected $request;
    private $warehouse_channel_type = 12;
    private $test_merchant = "Test Merchant";
    private $test_channels = "%[Development Client]%";
    private $correction_channels = "%Correction%";

    public function __construct($request)
    {
    	//$this->order = new Order;
        $this->request = $request;
        
    }
    	
    public function getDataTable() 
    {
        
        $channels = $this->request['channel'];
        $merchants = $this->request['merchant'];
        //$brands = response()->json($this->request->brand);
        $startdate = $this->request['startDate'];
        $newStartDate = date("Y-d-m", strtotime($startdate));
        $enddate = $this->request['endDate'];
        $newEndDate = date("Y-d-m", strtotime($enddate));

        // get all sales for the duration
        ini_set('memory_limit','-1');
        $date_range = [$newStartDate, $newEndDate];\Log::info($date_range);

        $sales = Order::leftJoin(
        \DB::raw("
            (select
                `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0
            ) `order_completed`
        "), 'order_completed.order_id', '=', 'orders.id')
        ->whereBetween('order_completed.completed_date', $date_range)
        ->where('status', Order::$completedStatus)
        ->get();

        $saleMasterData = array();
        foreach ($sales as $sale) {
            $items = $sale->itemSKUs;
            $itemArr = array();

            foreach ($items as $item) {
                if ($item->isChargeable()) {
                    if($item->tax_inclusive == true) {
                        $soldAmountWithoutGst = $item->sold_price - $item->tax;
                    } else {
                        $soldAmountWithoutGst = $item->sold_price;
                    }
                    $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                    $channel_sku = ChannelSKU::find($item->ref_id);

                    $itemArr[] = array(
                        'sku_id'    => $item->ref->sku->sku_id,
                        'channel_id'    => $channel_sku->channel_id,
                        'unit_price'    => $item->unit_price,
                        'sale_price'    => ($item->sale_price == 0)?$item->unit_price:$item->sale_price,
                        'total_amount_paid'     => $item->sold_price * $item->original_quantity,
                        'total_amount_paid_excl_gst'    => $soldAmountWithoutGst * $item->original_quantity,
                        'total_discount'    => $discount * $item->original_quantity,
                        'total_quantity' => $item->original_quantity
                    );
                    /*
                    if (array_key_exists($item->ref->sku->sku_id, $itemArr)) {
                        $itemArr[$item->ref->sku->sku_id]['sku_id'] = $item->ref->sku->sku_id;
                        $itemArr[$item->ref->sku->sku_id]['channel_id'] = $channel_sku->channel_id;
                        $itemArr[$item->ref->sku->sku_id]['unit_price'] = $item->unit_price;
                        $itemArr[$item->ref->sku->sku_id]['total_amount_paid'] += $item->sold_price;
                        $itemArr[$item->ref->sku->sku_id]['total_amount_paid_excl_gst'] += $soldAmountWithoutGst;
                        $itemArr[$item->ref->sku->sku_id]['total_discount'] += $discount;
                        $itemArr[$item->ref->sku->sku_id]['total_quantity']++;
                    } else {
                        $itemArr[$item->ref->sku->sku_id] = array();
                        $itemArr[$item->ref->sku->sku_id]['sku_id'] = $item->ref->sku->sku_id;
                        $itemArr[$item->ref->sku->sku_id]['channel_id'] = $channel_sku->channel_id;
                        $itemArr[$item->ref->sku->sku_id]['unit_price'] = $item->unit_price;
                        $itemArr[$item->ref->sku->sku_id]['total_amount_paid'] = $item->sold_price;
                        $itemArr[$item->ref->sku->sku_id]['total_amount_paid_excl_gst'] = $soldAmountWithoutGst;
                        $itemArr[$item->ref->sku->sku_id]['total_discount'] = $discount;
                        $itemArr[$item->ref->sku->sku_id]['total_quantity'] = 1;
                    }
                    */
                }
            }


            foreach ($itemArr as $itemTotals) {
                $sku = SKU::find($itemTotals['sku_id']);
                $product = Product::with('brand')->find($sku->product_id);
                $channel = Channel::with('channel_type', 'merchants')->find($itemTotals['channel_id']); 
              
            
                $reportData = [
                        'Merchant' => $merchant_get->name,
                        'Channel' => $channel->name, 
                        'Order Completed Date' =>  !($sale->orderDate($sale->id))?$sale->orderDate($sale->id):Carbon::createFromFormat('Y-m-d H:i:s', $sale->orderDate($sale->id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                        'Order No' => $sale->id,
                        'Third Party Order No' => $sale->tp_order_code,
                        //'Brand' => $product->getRelation('brand')->prefix,
                        'Hubwire SKU' => $sku->hubwire_sku,
                        'Quantity' => $itemTotals['total_quantity'],     
                        'Retail Price (Incl. GST)' => number_format($itemTotals['unit_price'], 2), // price per unit
                        //'Retail Price (Excl. GST)' => number_format($itemTotals['unit_price']/1.06, 2), // price per unit
                        'Listing Price (Incl. GST)'=> number_format($itemTotals['sale_price'], 2),
                        //'Listing Price (Excl. GST)'=> number_format($itemTotals['sale_price']/1.06, 2),
                        //'Total Sales (Incl. GST)' => number_format($itemTotals['total_amount_paid_excl_gst']*1.06, 2),
                        'Total Sales (Excl. GST)' => number_format($itemTotals['total_amount_paid_excl_gst'], 2) //(amount paid by customer exclusive GST)
                    ];
                $saleMasterData[] = $reportData;
                
            }
        } 
       
        
        \Log::info(print_r($saleMasterData,true));
        return $saleMasterData;
    }

    public function merchantStorageReport($merchant_id, $startdate, $enddate, $duration = '')
    {
        $dataArray = array();
        $summaries = array();
        ini_set('memory_limit','-1');
        $query = $this->merchantStorageQuery($merchant_id, $startdate, $enddate);
        
        $data = array();
        $i = 0;
        
        foreach($query as $storage)
        {
            $i++;
            $data[] = [
                        'NO #'          =>  $i, 
                        'Date'          =>  $storage->date_cal, 
                        'Hubwire SKU'   =>  $storage->hubwire_sku,
                        'Merchant SKU'  =>  $storage->sku_supplier_code,
                        'Product Name'  =>  $storage->product,
                        'Size'          =>  $storage->size,
                        'Color'         =>  $storage->color,
                        'Quantity'      =>  $storage->quantity
                      ]; 
        }

        $storages['data'] = $data;

        $storages['headers'] = [
            'NO #',
            'Date',
            'Hubwire SKU',
            'Merchant SKU',
            'Product Name',
            'Size',
            'Color',
            'Quantity'
        ];

        $data_summary = collect($data);

        $storages['summary'] = [
                '',
                '',
                '',
                '',
                '',
                '',
                'Nett',
                $data_summary->sum('Quantity')
            ];

        $dataArray['Storage']['tables'][] = $storages;
        return $dataArray;
    }

    public function merchantStorageQuery($merchant_id, $startdate, $enddate)
    {
        $query = "select date_cal, hubwire_sku, sku_supplier_code, product,
                        final_table.sku_id, merchant_id, brand_id, brand, 
                        IF(skuql.quantity IS NULL,0,skuql.quantity) as quantity,size,color  from 
                        (
                        select sku.sku_id as sku_id, 
                        sku_calendar.date_cal as date_cal, 
                        max(xlog.id) as xlog_id,p.name as product,
                        sku.hubwire_sku, p.merchant_id,p.brand_id,
                        b.prefix as brand, sku.sku_supplier_code,
                        MAX(size.`option_value`) AS size,
                        MAX(colour.`option_value`) AS color
                        from (                        
                        select sku_id, date_cal from sku, (
                        select curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as date_cal
                            from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a
                            cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b
                            cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c
                        ) calendar
                        ) sku_calendar
                        left join (
                            select id, sku_id, created_at as log_date from sku_quantity_log
                        )xlog on ( xlog.sku_id = sku_calendar.sku_id  
                        and xlog.log_date <= sku_calendar.date_cal )
                        left join sku on sku.sku_id = sku_calendar.sku_id
                        LEFT JOIN sku_combinations ON sku_combinations.sku_id = sku.sku_id
                        LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = 'Colour') as colour ON sku_combinations.option_id = colour.option_id
                        LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = 'Size') as size ON sku_combinations.option_id = size.option_id
                        left join products p on p.id = sku.product_id
                        left join brands b on b.id = p.brand_id
                        where 
                        date_cal between '".$startdate."' and '".$enddate."'
                        and p.merchant_id = '".$merchant_id."'
                        and sku.deleted_at is null
                        and p.deleted_at is null
                        and b.deleted_at is null
                        group by sku.sku_id, sku_calendar.date_cal
                        order by sku_calendar.date_cal asc, sku_calendar.sku_id asc
                        ) final_table 
                        left join sku_quantity_log skuql on skuql.id = xlog_id";
            // \Log::info($query);
            return \DB::select(DB::raw($query));
    }

    public function merchantSalesInventoryReport($merchant_id, $startdate, $enddate, $duration = '') {
        
        $dataArray = array();
        $summaries = array();
        
        ini_set('memory_limit','-1');
        /**
        ** Inventory Report
        **/
        $inventory = $this->merchantInventoryQuery($merchant_id, $startdate, $enddate);
        array_walk($inventory['data'],function(&$array, $key){
            $array = ['NO #'=>($key+1)] + $array ;
        });
        
        $inventory['headers'] = [
            'NO #',
            'Brand',
            'Hubwire SKU',
            'Merchant SKU',
            'Product Name',
            'Size',
            'Color',
            'Stock (Start)',
            'Inbound',
            'Outbound',
            'Adjustment',
            'Sold',
            'Returns',
            'Stock (End)'
        ];
        $dataArray['Inventory']['tables'][] = $inventory;


        /**
        ** Sales Report
        */
        $sales = $this->merchantSalesQuery($merchant_id, $startdate, $enddate);
        $sales['headers'] = [
            'Channel',
            'Date Ordered',
            'Order #',
            '3rd Party Order #',
            'Brand',
            'Hubwire SKU',
            'Merchant SKU',
            'Product Name',
            'Size',
            'Color',
            'Quantity',
            'Unit Price (Exc GST)',
            'Discounts',
            'Total Sales (Exc GST)',
            'FM Hubwire Fees (Exc GST)'
        ];
        $dataArray['Sales']['tables']['Delivered Items'] = $sales;

        $total_transaction_fee = collect($sales['data'])->sum('FM Hubwire Fees (Exc GST)');
        $total_transaction = collect($sales['data'])->sum('Quantity');

        /*
        ** Returns Report
        */

        $returns = $this->merchantReturnsQuery($merchant_id, $startdate, $enddate);
        $returns['headers'] = [
            'Channel',
            'Date Ordered',
            'Order #',
            '3rd Party Order #',
            'Brand',
            'Hubwire SKU',
            'Merchant SKU',
            'Product Name',
            'Size',
            'Color',
            'Quantity',
            'Unit Price (Exc GST)',
            'Discounts',
            'Total Sales (Exc GST)',
            'FM Hubwire Fees (Exc GST)'
        ];
        $dataArray['Sales']['tables']['Returned Items'] = $returns;

        $summary_headers = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'Detail',
            'Quantity',
            'Unit Price (Exc GST)',
            'Discounts',
            'Total Sales (Exc GST)',
            'FM Hubwire Fees (Exc GST)'
        ];
        
        /**
        ** Summary Table
        */
        /*
        $summary = array_combine($summary_headers,array_filter(array_values($sales['summary']), function($value){
            return ($value !== null && $value !== false && $value !== '');
        }));
        */
        $summary = $sales['summary'];
        $summary['TOTAL'] = 'Delivered';
        $summaries[] = $summary;
        
        /*
        $summary = array_combine($summary_headers,array_filter(array_values($returns['summary']), function($value){
            return ($value !== null && $value !== false && $value !== '');
        }));
        */
        $summary = $returns['summary'];
        $summary['TOTAL'] = 'Returned';
        $summaries[] = $summary;

       
        $data_summary = collect($summaries);

        $dataArray['Sales']['tables'][] = [
            'headers' => $summary_headers,
            'data' => $data_summary->toArray(), 
            'summary' => [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Nett',
                $data_summary->first()['Quantity'] - $data_summary->last()['Quantity'],
                $data_summary->first()['Unit Price (Exc GST)'] - $data_summary->last()['Unit Price (Exc GST)'] ,
                '-',
                $data_summary->first()['Total Sales (Exc GST)'] - $data_summary->last()['Total Sales (Exc GST)'] ,
                $data_summary->first()['FM Hubwire Fees (Exc GST)'] - $data_summary->last()['FM Hubwire Fees (Exc GST)']
            ]
        ];

        if(strcasecmp($duration, 'monthly') == 0)
        {
            // \Log::info($duration);
            //calculate Inbound and Outbound fee by contracts (brand_id + merchant_id)
            $total_inbound = 0;
            $total_inbound_fee = 0;
            $inbound_rate = 0;

            $total_outbound = 0;
            $total_outbound_fee = 0;
            $outbound_rate = 0;

            $min_guarantee = 0;

            $s_date = Carbon::createFromFormat('Y-m-d H:i:s',$startdate)->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d');
            $e_date = Carbon::createFromFormat('Y-m-d H:i:s',$enddate)->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d');
            
            foreach($inventory['brandsItems'] as $brand_id => $items)
            {
                // \Log::info(print_r($items, true));
                // find contract for this brand + merchant
                $contract = Contract::where('merchant_id','=',$merchant_id)
                            ->where('brand_id', '=', $brand_id)
                            ->whereRaw(" start_date <= '".$s_date."' " )
                            ->whereRaw(" ( end_date >='".$e_date."' OR end_date IS NULL ) " )
                            ->first();
                
                // contract not found for this brand, go to next one
                if(empty($contract)) continue;
                // \Log::info(print_r($contract->toArray(), true));

                $inbound_rate = $contract->inbound_fee;
                $outbound_rate = $contract->outbound_fee;
                $min_guarantee = ( ($contract->min_guarantee==1) && ($contract->guarantee > $min_guarantee) )?$contract->guarantee : $min_guarantee;

                // convert array to Collection so that can take adv. of the features
                $data = is_array($items)?collect($items):$items;
                // \Log::info(print_r($data, true));

                //inbound
                $inbound = $data->sum('Inbound'); 
                $inbound_fee = $inbound*$contract->inbound_fee;
                // $total_inbound += $inbound;
                // $total_inbound_fee += $inbound_fee;

                //outbound
                $outbound = $data->sum('Outbound');
                $outbound_fee = $outbound*$contract->outbound_fee;
                // $total_outbound += $outbound;
                // $total_outbound_fee += $outbound_fee;

                // save information in fee table
                $fee = Fee::firstOrNew(['contract_id'=>$contract->id,'start_date' => $s_date, 'end_date'=>$e_date]);
                $fee->inbound = $inbound;
                $fee->inbound_fee = $inbound_fee;
                $fee->outbound = $outbound;
                $fee->outbound_fee = $outbound_fee;
                $fee->contract_id = $contract->id;
                $fee->save();

            }
            $collection = collect($inventory['data']);
            $total_inbound = $collection->sum('Inbound');
            $total_inbound_fee = $total_inbound*$inbound_rate;

            $total_outbound = $collection->sum('Outbound');
            $total_outbound_fee = $total_outbound*$outbound_rate;

            //calculate Shipped fee by contracts (brand_id + merchant_id)
            $total_shipped = 0;
            $total_shipped_fee = 0;
            $shipped_rate = 0;
            $transaction_fee = 0;
            
            foreach($sales['brandsItems'] as $brand_id => $items)
            {
                // \Log::info(print_r($items, true));
                // find contract for this brand + merchant
                $contract = Contract::where('merchant_id','=',$merchant_id)
                            ->where('brand_id', '=', $brand_id)
                            ->whereRaw(" start_date <= '".$s_date."' " )
                            ->whereRaw(" ( end_date >='".$e_date."' OR end_date IS NULL ) " )
                            ->first();
                
                // contract not found for this brand, go to next one
                if(empty($contract)) continue;
                // \Log::info(print_r($contract->toArray(), true));

                $shipped_rate = $contract->shipped_fee;    
                $min_guarantee = ( ($contract->min_guarantee==1) && ($contract->guarantee > $min_guarantee) )?$contract->guarantee : $min_guarantee;
                    

                // convert array to Collection so that can take adv. of the features
                $data = is_array($items)?collect($items):$items;
                // \Log::info(print_r($data, true));

                //shipped
                $shipped = $data->sum('quantity'); 
                $shipped_fee = $shipped*$contract->shipped_fee;
                $transaction_fee = $data->sum(function ($item) {
                                        return number_format($item->hw_fee,2);
                                    });
                //$transaction_fee = $data->sum('hw_fee');
                // dd($transaction_fee);

                // $total_shipped += $shipped;
                // $total_shipped_fee += $shipped_fee;

                
                // save information in fee table
                $fee = Fee::firstOrNew(['contract_id'=>$contract->id,'start_date' => $s_date, 'end_date'=>$e_date]);
                $fee->transaction = $shipped;
                $fee->transaction_fee = $transaction_fee;
                $fee->shipped = $shipped;
                $fee->shipped_fee = $shipped_fee;
                $fee->contract_id = $contract->id;
                $fee->save();

            }
            $collection = collect($sales['data']);
            $total_shipped = $collection->sum('Quantity');
            $total_shipped_fee = $total_shipped*$shipped_rate;

            //calculate Shipped fee by contracts (brand_id + merchant_id)
            $total_return = 0;
            $total_return_fee = 0;
            $return_rate = 0;

            foreach($returns['brandsItems'] as $brand_id => $items)
            {
                // \Log::info(print_r($items, true));
                // find contract for this brand + merchant
                $contract = Contract::where('merchant_id','=',$merchant_id)
                            ->where('brand_id', '=', $brand_id)
                            ->whereRaw(" start_date <= '".$s_date."' " )
                            ->whereRaw(" ( end_date >='".$e_date."' OR end_date IS NULL ) " )
                            ->first();
                
                // contract not found for this brand, go to next one
                if(empty($contract)) continue;
                // \Log::info(print_r($contract->toArray(), true));

                $return_rate = $contract->return_fee;
                $min_guarantee = ( ($contract->min_guarantee==1) && ($contract->guarantee > $min_guarantee) )?$contract->guarantee : $min_guarantee;
                
                // convert array to Collection so that can take adv. of the features
                $data = is_array($items)?collect($items):$items;
                // \Log::info(print_r($data, true));

                //return
                $return = $data->sum('original_quantity'); 
                $return_fee = $return*$contract->return_fee;
                // $total_return += $return;
                // $total_return_fee += $return_fee;

                
                // save information in fee table
                $fee = Fee::firstOrNew(['contract_id'=>$contract->id,'start_date' => $s_date, 'end_date'=>$e_date]);
                $fee->return = $return;
                $fee->return_fee = $return_fee;
                $fee->contract_id = $contract->id;
                $fee->save();

            }
            $collection = collect($returns['data']);
            $total_return = $collection->sum('Quantity');
            $total_return_fee = $total_return*$return_rate;

            //calculate Storage fee by contracts (brand_id + merchant_id)
            $total_storage = 0;
            $total_storage_fee = 0;
            $storage_rate = 0;
            // get the highest quantity for the merchant (each channel_sku)

            $storages = collect($this->merchantStorageQuery($merchant_id, $startdate, $enddate));

            $brands = $storages->groupBy('brand_id');

            foreach($brands as $brand_id => $data)
            {
                // \Log::info(print_r($items, true));
                // find contract for this brand + merchant
                $contract = Contract::where('merchant_id','=',$merchant_id)
                            ->where('brand_id', '=', $brand_id)
                            ->whereRaw(" start_date <= '".$s_date."' " )
                            ->whereRaw(" ( end_date >='".$e_date."' OR end_date IS NULL ) " )
                            ->first();
                
                // contract not found for this brand, go to next one
                if(empty($contract)) continue;
                // \Log::info(print_r($contract->toArray(), true));

                $storage_rate = $contract->storage_fee;
                $min_guarantee = ( ($contract->min_guarantee==1) && ($contract->guarantee > $min_guarantee) )?$contract->guarantee : $min_guarantee;

                // convert array to Collection so that can take adv. of the features
                $data = is_array($data)?collect($data):$data;

                // \Log::info(print_r($data, true));

                //storage
                $storage = $data->sum('quantity'); 
                $storage_fee = $storage*$contract->storage_fee;
                // $total_storage += $storage;
                // $total_storage_fee += $storage_fee;
                
                // save information in fee table
                $fee = Fee::firstOrNew(['contract_id'=>$contract->id,'start_date' => $s_date, 'end_date'=>$e_date]);
                $fee->storage = $storage;
                $fee->storage_fee = $storage_fee;
                $fee->contract_id = $contract->id;
                $fee->save();
            }
            $total_storage = $storages->sum('quantity');
            $total_storage_fee = $total_storage*$storage_rate;

            // Fee Charges

            $table1 = array();
            
            $table1['headers'] = [
                'No',
                'Activity',
                'Unit',
                'Fees per item (RM)',
                'Total Fees (RM)'
            ];

            $total_fee = $total_inbound_fee+$total_storage_fee+$total_outbound_fee+$total_shipped_fee+$total_return_fee;
            $table1['data'][] = ['1','Inbound',$total_inbound,$inbound_rate,$total_inbound_fee];
            $table1['data'][] = ['2','Storage',$total_storage,$storage_rate,$total_storage_fee];
            $table1['data'][] = ['3','Outbound',$total_outbound,$outbound_rate,$total_outbound_fee];
            $table1['data'][] = ['4','Shipped',$total_shipped,$shipped_rate,$total_shipped_fee];
            $table1['data'][] = ['5','Returns',$total_return,$return_rate,$total_return_fee];
            $table1['summary'] = ['','','','Warehousing and logistics subtotal',($total_fee)];

            $dataArray['FM Hubwire Fees']['tables']['WAREHOUSING AND LOGISTICS FEES'] = $table1;
            
            $table2 = array();
            
            $table2['headers'] = [
                'No',
                'Activity',
                'Unit',
                'Fees per item (RM)',
                'Total Fees (RM)'
            ];

            $retail_fee = $total_transaction_fee<$min_guarantee?$min_guarantee:$total_transaction_fee;

            $table2['data'][] = ['1','Retail system operations','-','% of retail sales',$total_transaction_fee];
            $table2['data'][] = ['2','Monthly Minimum Guarantee','-','Whichever is higher',$min_guarantee];
            $table2['summary'] = ['','','','Retail system operations subtotal',$retail_fee];

            $grand_total = $retail_fee + $total_fee;
            $dataArray['FM Hubwire Fees']['tables']['RETAIL SYSTEM OPERATIONS FEES'] = $table2;
            $dataArray['FM Hubwire Fees']['tables'][] = [
                'headers' => [],
                'data' => [
                    ['','','','TOTAL exc GST (RM)',$grand_total],
                    ['','','','GST 6%',$grand_total*0.06],
                    ['','','','TOTAL inc GST (RM)',$grand_total+($grand_total*0.06)],
                ], 
                'summary' => []
            ];
        }

        return $dataArray;
    }

    
    public function merchantSalesQuery($merchant_id, $startDateTime, $endDateTime, $timezone ="+08:00") {
        $response = array();
        $date_range = [$startDateTime, $endDateTime];
        // $date_range = ["2016-01-01 16:00:00", "2016-01-31 16:00:00"];
        // $merchant_id = 17;
        
        $items = OrderItem::chargeable()->leftJoin(
        \DB::raw("
            (select
                `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0 group by order_id
            ) `order_completed`

        "), 'order_completed.order_id', '=', 'order_items.order_id')
        ->leftJoin('orders','orders.id','=','order_items.order_id')
        ->whereBetween('order_completed.completed_date', $date_range)
        ->where('orders.status', Order::$completedStatus)
        ->where('order_items.merchant_id', $merchant_id)
        ->get();
        
        $reportData = array();
        $totalQuantity = 0;
        $totalUnitPrice = 0;
        $totalSales = 0;
        $totalFee = 0;
        $brandsItems = array();
        foreach($items as $item)
        {
            $brandsItems[$item->ref->product->brand_id][] = $item; 
            $fee = number_format($item->hw_fee, 2)  / 1.06;

            $order = $item->order;
            $channel = $order->channel;
            $unit_price     =   $item->unit_price/(1.06);
            $total_sales    =   !empty($channel->channel_detail->sale_amount) ? (($item->sale_price>0?$item->sale_price:$item->unit_price)/1.06)*$item->original_quantity : $item->sold_price;
            $totalQuantity  +=  $item->original_quantity;
            $totalUnitPrice +=  $unit_price;
            $totalSales     +=  $total_sales;
            $totalFee       +=  $fee;

            $reportData[] = [
                'Channel'                   => $channel->name,
                'Date Ordered'              => Carbon::createFromFormat('Y-m-d H:i:s',$order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                'Order #'                   => $order->id,
                '3rd Party Order #'         => $order->tp_order_code,
                'Brand'                     => $item->ref->product->brands->name,
                'Hubwire SKU'               => $item->ref->sku->hubwire_sku,
                'Merchant SKU'              => $item->ref->sku->sku_supplier_code,
                'Product Name'              => $item->ref->product->name,
                'Size'                      => $item->ref->sku->size,
                'Color'                     => $item->ref->sku->color,
                'Quantity'                  => $item->original_quantity,
                'Unit Price (Exc GST)'      => number_format($unit_price,2,'.',''),
                'Discounts'                 => round(($item->sale_price > 0 ? ($item->unit_price - $item->sale_price)/$item->unit_price*100 : 0),0).'%',
                'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                'FM Hubwire Fees (Exc GST)' => $fee,
            ];
        }
        
        $response['summary'] = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'TOTAL'=> 'TOTAL',
            'Quantity'                  =>  $totalQuantity,
            'Unit Price (Exc GST)'      =>  number_format($totalUnitPrice,2,'.',''),
            'Discounts'                 =>  '-',
            'Total Sales (Exc GST)'     =>  number_format($totalSales,2,'.',''),
            'FM Hubwire Fees (Exc GST)' =>  number_format($totalFee,2,'.','')
        ];
        $response['data'] = $reportData;
        $response['brandsItems'] = collect($brandsItems);
        
        return $response;
    }

    public function merchantReturnsQuery($merchant_id, $startDateTime, $endDateTime, $timezone ="+08:00") {
        
        $response = array();
        $date_range = [$startDateTime, $endDateTime];
        // $date_range = ["2016-01-01 16:00:00", "2016-01-31 16:00:00"];
        // $merchant_id = 17;
        
        $returns = ReturnLog::select('return_log.*')
                    ->with('item')
                    ->leftJoin('order_items','order_items.id','=','return_log.order_item_id')
                    ->whereBetween('return_log.completed_at', $date_range)
                    ->where('return_log.status','Restocked')
                    ->where('order_items.status','Returned')
                    ->where('order_items.merchant_id',$merchant_id)
                    ->get();

        $reportData = array();
        $totalQuantity = 0;
        $totalUnitPrice = 0;
        $totalSales = 0;
        $totalFee = 0;
        $brandsItems = array();
        foreach($returns as $return)
        {

            $item           =   $return->item;
            
            $brandsItems[$item->ref->product->brand_id][] = $item; 
            
            $order          =   $item->order;
            $channel        =   $order->channel;
            $unit_price     =   $item->unit_price/(1.06);
            $using_price    =   ($channel->channel_detail->sale_amount) ? $item->sale_price : $item->sold_price;
            $total_sales    =   (($using_price>0?$using_price:$item->unit_price)/1.06)*$return->quantity;
            $totalQuantity  +=  $return->quantity;
            $totalUnitPrice +=  $unit_price;
            $totalSales     +=  $total_sales;
            //$totalFee       +=  0;
            $reportData[] = [
                'Channel'                   => $channel->name,
                'Date Ordered'              => Carbon::createFromFormat('Y-m-d H:i:s',$order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                'Order #'                   => $order->id,
                '3rd Party Order #'         => $order->tp_order_code,
                'Brand'                     => $item->ref->product->brands->name,
                'Hubwire SKU'               => $item->ref->sku->hubwire_sku,
                'Merchant SKU'              => $item->ref->sku->sku_supplier_code,
                'Product Name'              => $item->ref->product->name,
                'Size'                      => $item->ref->sku->size,
                'Color'                     => $item->ref->sku->color,
                'Quantity'                  => $return->quantity,
                'Unit Price (Exc GST)'      => number_format($unit_price,2,'.',''),
                'Discounts'                 => round(($using_price > 0 ? ($item->unit_price - $using_price)/$item->unit_price*100 : 0),0).'%',
                'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                'FM Hubwire Fees (Exc GST)' => '0.00',
            ];

        }
        $response['summary'] = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'TOTAL'=> 'TOTAL',
            'Quantity'                  =>  $totalQuantity,
            'Unit Price (Exc GST)'      =>  number_format($totalUnitPrice,2,'.',''),
            'Discounts'                 =>  '-',
            'Total Sales (Exc GST)'     =>  number_format($totalSales,2,'.',''),
            'FM Hubwire Fees (Exc GST)' =>  number_format($totalFee,2,'.','')
        ];
        $response['data'] = $reportData;
        $response['brandsItems'] = collect($brandsItems);
        return $response;
    }


    public function merchantInventoryQuery($merchant_id, $startDateTime, $endDateTime, $timezone ="+08:00") 
    {
        $response = array();

        $query = 'SELECT IF (brands.`name` IS NOT NULL , brands.`name` , products.`brand`) AS Brand, 
                products.`brand_id`,
                sku.`hubwire_sku` AS "Hubwire SKU",
                sku.`sku_supplier_code` AS "Merchant SKU",';
        
        $query .= 'products.`name` AS "Product Name",
                MAX(size.`option_value`) AS Size,
                MAX(colour.`option_value`) AS Color,
                IF(available_stock_start.`quantity` IS NOT NULL, available_stock_start.`quantity`, 0)+
                IF(reserved_start.`quantity` IS NOT NULL, reserved_start.`quantity`, 0)+
                IF(unreceived_st_start.`quantity` IS NOT NULL, unreceived_st_start.`quantity`, 0) +
                IF(correction.`quantity` IS NOT NULL, correction.`quantity`, 0) AS "Stock (Start)",';
        /*  
            Breakdown for stock in hand
                IF(available_stock_start.`quantity` IS NOT NULL, available_stock_start.`quantity`, 0) AS "Stock In-hand Start",
                IF(reserved_start.`quantity` IS NOT NULL, reserved_start.`quantity`, 0) AS "Reserved Start",
                IF(unreceived_st_start.`quantity` IS NOT NULL, unreceived_st_start.`quantity`, 0) AS "Unreceived ST Start",';
        */
        $query .='IF(purchases.`inbound` IS NOT NULL, purchases.`inbound`, 0) AS "Inbound",
                IF(reject_items.`outbound` IS NOT NULL, reject_items.`outbound`, 0) AS "Outbound",
                IF(adjustments.`adjustment` IS NOT NULL, adjustments.`adjustment`, 0) AS "Adjustment",
                IF(completed_orders.sold IS NOT NULL, completed_orders.`sold`, 0) as "Sold",
                IF(return_processed.return_qty IS NOT NULL, return_processed.return_qty, 0) AS "Returns",
                IF(available_stock_end.`quantity` IS NOT NULL, available_stock_end.`quantity`, 0) +
                IF(reserved_end.`quantity` IS NOT NULL, reserved_end.`quantity`, 0) +
                IF(unreceived_st_end.`quantity` IS NOT NULL, unreceived_st_end.`quantity`, 0) AS "Stock (End)"';
        /* Breakdown for stock in hand, reserved
        $query .= 'IF(reserved_end.`quantity` IS NOT NULL, reserved_end.`quantity`, 0) +
                IF(readytoship_end.`quantity` IS NOT NULL, readytoship_end.`quantity`, 0) AS "Reserved",';
        $query .='IF(available_stock_end.`quantity` IS NOT NULL, available_stock_end.`quantity`, 0) AS "Availble Stock End",
                IF(reserved_end.`quantity` IS NOT NULL, reserved_end.`quantity`, 0) AS "Reserved End",
                IF(unreceived_st_end.`quantity` IS NOT NULL, unreceived_st_end.`quantity`, 0) AS "Unreceived ST End"';

        */
        $query .=' FROM sku
                LEFT JOIN sku_combinations ON sku_combinations.sku_id = sku.sku_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Colour") as colour ON sku_combinations.option_id = colour.option_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Size") as size ON sku_combinations.option_id = size.option_id
                LEFT JOIN products ON sku.product_id = products.id
                LEFT JOIN brands ON products.brand_id = brands.id
                LEFT JOIN merchants ON brands.merchant_id = merchants.id
                LEFT JOIN (
                    SELECT purchase_items.sku_id, min(purchase_batches.receive_date) AS receive_date
                    FROM purchase_batches
                    LEFT JOIN purchase_items ON purchase_batches.batch_id = purchase_items.batch_id
                    WHERE purchase_batches.receive_date < "'.$endDateTime.'"
                    AND purchase_batches.batch_status = 1
                    GROUP BY purchase_items.sku_id
                ) product_procured ON sku.sku_id = product_procured.sku_id
                LEFT JOIN (
                    SELECT MAX(id) as id,sku_id
                    FROM sku_quantity_log
                    WHERE sku_quantity_log.created_at <= "'.$startDateTime.'"
                    GROUP BY sku_id
                ) stock_start_ids ON stock_start_ids.sku_id = sku.sku_id
                LEFT JOIN sku_quantity_log available_stock_start on stock_start_ids.id = available_stock_start.id
                LEFT JOIN (
                    SELECT sku_id , sum(quantity_new) as quantity from
                    (SELECT channel_sku_id, MAX(id) as id
                    FROM reserved_quantities_log
                    WHERE reserved_quantities_log.created_at <= "'.$startDateTime.'"
                    GROUP BY channel_sku_id) reserved_quantity_ids
                    LEFT JOIN reserved_quantities_log on reserved_quantity_ids.id = reserved_quantities_log.id
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = reserved_quantities_log.channel_sku_id
                    GROUP BY channel_sku.sku_id
                )reserved_start ON reserved_start.sku_id = sku.sku_id
                LEFT JOIN (
                SELECT
                    di.sku_id , sum(di.quantity) as quantity
                FROM
                    delivery_orders_items di
                LEFT JOIN delivery_orders d_o ON di.do_id = d_o.id
                WHERE
                    di.deleted_at IS NULL
                        AND (d_o.deleted_at IS NULL OR d_o.deleted_at >= "'.$startDateTime.'")
                        AND (d_o.receive_at IS NULL OR d_o.receive_at >= "'.$startDateTime.'")
                        AND d_o.sent_at < "'.$startDateTime.'"
                        GROUP BY di.sku_id
                ) unreceived_st_start on unreceived_st_start.sku_id = sku.sku_id
                LEFT JOIN (
                    SELECT purchase_items.sku_id, SUM(purchase_items.item_quantity) AS inbound, purchase_batches.batch_id
                    FROM purchase_batches
                    LEFT JOIN purchase_items ON purchase_batches.batch_id = purchase_items.batch_id
                    WHERE purchase_batches.receive_date >= "'.$startDateTime.'" AND purchase_batches.receive_date < "'.$endDateTime.'"
                    AND purchase_batches.batch_status = 1
                    GROUP BY purchase_items.sku_id
                ) purchases ON sku.sku_id = purchases.sku_id
                LEFT JOIN (
                    SELECT channel_sku.sku_id, sum(return_log.quantity) AS return_qty
                    FROM return_log
                    LEFT JOIN order_items on order_items.id  = return_log.order_item_id and order_items.ref_type = "ChannelSKU"
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = order_items.ref_id
                    WHERE return_log.completed_at >= "'.$startDateTime.'"
                    AND return_log.completed_at < "'.$endDateTime.'"
                    AND return_log.quantity <> 0
                    AND return_log.order_item_id <> 0
                    AND order_items.status <> "Cancelled"
                    GROUP BY channel_sku.sku_id
                ) return_processed ON sku.sku_id = return_processed.sku_id
                 LEFT JOIN (
                    SELECT channel_sku.sku_id, SUM(order_items.original_quantity) AS sold
                    FROM order_items
                    LEFT JOIN orders ON orders.id = order_items.order_id
                    LEFT JOIN channel_sku ON channel_sku.channel_sku_id = order_items.ref_id
                    LEFT JOIN order_status_log ON order_status_log.to_status = "Completed" and order_status_log.order_id = orders.id
                    WHERE order_status_log.created_at >= "'.$startDateTime.'"
                    AND order_status_log.created_at <= "'.$endDateTime.'"
                    AND orders.status >= 32
                    AND order_items.status in ("Verified","Returned")
                    AND order_status_log.user_id = 0
                    GROUP BY channel_sku.sku_id
                ) completed_orders ON sku.sku_id = completed_orders.sku_id
                LEFT JOIN (
                    SELECT reject_log.sku_id, SUM(reject_log.quantity) AS outbound
                    FROM reject_log
                    WHERE reject_log.created_at >= "'.$startDateTime.'" AND reject_log.created_at < "'.$endDateTime.'"
                    AND outbound = 1 GROUP BY reject_log.sku_id
                ) reject_items ON sku.sku_id = reject_items.sku_id
                LEFT JOIN (
                    SELECT reject_log.sku_id, SUM(reject_log.quantity) AS adjustment
                    FROM reject_log
                    WHERE reject_log.created_at >= "'.$startDateTime.'" AND reject_log.created_at < "'.$endDateTime.'"
                    AND outbound = 0 GROUP BY reject_log.sku_id
                ) adjustments ON sku.sku_id = adjustments.sku_id
                LEFT JOIN(
                    SELECT MAX(id) as id,sku_id
                    FROM sku_quantity_log
                    WHERE sku_quantity_log.created_at <= "'.$endDateTime.'"
                    GROUP BY sku_id
                ) stock_end_ids ON stock_end_ids.sku_id = sku.sku_id
                LEFT JOIN sku_quantity_log available_stock_end on stock_end_ids.id = available_stock_end.id
                LEFT JOIN (
                    SELECT sku_id , sum(quantity_new) as quantity from
                    (SELECT channel_sku_id, MAX(id) as id
                    FROM reserved_quantities_log
                    WHERE reserved_quantities_log.created_at <= "'.$endDateTime.'"
                    GROUP BY channel_sku_id) reserved_quantity_ids
                    LEFT JOIN reserved_quantities_log on reserved_quantity_ids.id = reserved_quantities_log.id
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = reserved_quantities_log.channel_sku_id
                    GROUP BY channel_sku.sku_id
                )reserved_end ON reserved_end.sku_id = sku.sku_id
                LEFT JOIN (
                SELECT
                di.sku_id , sum(di.quantity) as quantity
                FROM
                    delivery_orders_items di
                LEFT JOIN delivery_orders d_o ON di.do_id = d_o.id
                WHERE
                    di.deleted_at IS NULL
                        AND (d_o.deleted_at IS NULL OR d_o.deleted_at >= "'.$endDateTime.'")
                        AND (d_o.receive_at IS NULL OR d_o.receive_at >= "'.$endDateTime.'")
                        AND d_o.sent_at < "'.$endDateTime.'"
                        GROUP BY di.sku_id
                ) unreceived_st_end on unreceived_st_end.sku_id = sku.sku_id
                LEFT JOIN 
                (
                    select sku_id , sum(quantity) as quantity from stock_correction where corrected_at between "'.$startDateTime.'" and "'.$endDateTime.'" group by sku_id
                )correction on correction.sku_id = sku.sku_id
                WHERE merchants.id not in ("'.$this->test_merchant.'") AND ';

        
            $query = $query . '(merchants.deleted_at IS NULL OR merchants.deleted_at > "'.$endDateTime.'")
            AND merchants.status = "Active" and merchants.id = "'.$merchant_id.'"
            AND (products.deleted_at IS NULL OR products.deleted_at > "'.$endDateTime.'")
            AND (sku.deleted_at IS NULL OR sku.deleted_at > "'.$endDateTime.'")
            AND (brands.deleted_at IS NULL OR brands.deleted_at > "'.$endDateTime.'")
            AND sku.`created_at` < "'.$endDateTime.'"';
        
        $query .= ' GROUP BY sku.sku_id
                    ORDER BY products.created_at ASC, hubwire_sku ASC';

        $result = DB::select(DB::raw($query));
        $inventory = collect($result);
        $response['brandsItems'] = $inventory->groupBy('brand_id');
        $inventory = $inventory->each(function ($item, $key) {
            unset($item->brand_id);
        });
        $response['data'] = json_decode(json_encode($inventory->toArray()), true);
        $response['summary'] = [
            '',
            '',
            '',
            '',
            '',
            '',
            'TOTAL',
            $inventory->sum('Stock (Start)'),
            $inventory->sum('Inbound'),
            $inventory->sum('Outbound'),
            $inventory->sum('Adjustment'),
            $inventory->sum('Sold'),
            $inventory->sum('Returns'),
            $inventory->sum('Stock (End)')
        ];

        return $response;
    }

}
