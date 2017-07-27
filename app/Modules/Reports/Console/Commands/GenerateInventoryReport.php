<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;
use App\Models\Admin\Order;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use DB;
use Storage;
use Log;
use Carbon\Carbon;
use Excel;
use App\Services\Mailer;

class GenerateInventoryReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'reports:generateInventoryReport {startdate} {enddate} {emails} {duration?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Inventory Report';

    private $warehouse_channel_type = 12;
    private $test_merchant = "Test Merchant";
    private $test_channels = "%[Development Client]%";
    private $correction_channels = "%Correction%";

    protected $reportsDirectory = 'reports/inventory';
    protected $reportsDirectoryChannel = 'reports/channel_inventory';
    protected $inventoryStockEndDirectory = 'reports/inventory_stock_end';

    protected $mailer;

    protected $masterData = array();
    protected $deletedData = array();

    /**
     * Create a new command instance.
     *
     * @return void
     */

    public function __construct(Mailer $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $startdate = $this->argument('startdate');
        $enddate = $this->argument('enddate');
        $emails = $this->argument('emails');
        $duration = ($this->argument('duration') != '' ? $this->argument('duration') : '');

        $this->info('Generating report from '. $startdate . ' to '. $enddate);


        //loop setting
        $loopTwice = 2;
        for ($i = 0; $i < $loopTwice; $i++){

            if ($i == 0){
                //Inventory filename
                $resultSet = $this->generateReport($startdate, $enddate, $this->mailer, $duration);
                $filename = 'inventory_report_'.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('YmdHis');
                $fileType = 'xls';
                $excel_start = microtime(true);
            } elseif ($i ==1) {
                //Channel Inventory filename
                $resultSet = $this->generateReportChannel($startdate, $enddate, $this->mailer, $duration);
                $filename = 'channel_inventory_report_'.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('YmdHis');
                $fileType = 'csv';
                $this->info("generateReport completed"); 
                $excel_start = microtime(true);
            }       

        // timestamp in UTC
        // create excel file
        $excel = $this->createExcel($resultSet, $startdate, $enddate, $filename, $duration, $fileType);
        $this->info("excel created");
        $excel_end = microtime(true);
        $excel_total_exec_time = ($excel_end - $excel_start)/60;

        $this->info('<b>Excel('.$fileType.') Execution Time :</b> '.$excel_total_exec_time.' Mins');
        // move file to S3

            if ($i == 0){
                $uploadedfile = Storage::disk('local')->get('reports/'.$excel['file']);
                $s3path = $this->reportsDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$excel['file'];
                $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
            } elseif ($i ==1) {
                $uploadedfileChannel = Storage::disk('local')->get('reports/'.$excel['file']);
                $s3pathChannel = $this->reportsDirectoryChannel.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$excel['file'];
                $s3uploadChannel = Storage::disk('s3')->getDriver()->put($s3pathChannel, $uploadedfileChannel, ['ContentType' => 'application/octet-stream']);
            }
        }

        if($s3uploadChannel){
            Storage::delete($excel['file']);

            $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
            $email_data['url'] = env('AWS_S3_URL').$s3path;
            $email_data['url2'] = env('AWS_S3_URL').$s3pathChannel;
            $email_data['email'] = $emails;
            $email_data['subject'] = $duration . ' Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').')';
            $email_data['report_type'] = 'Inventory';
            $email_data['duration'] = $duration;
            $email_data['startdate'] = $startdate->format('Y-m-d');
            $email_data['enddate'] = $enddate->format('Y-m-d');
            $this->mailer->scheduledReport($email_data);
        }

        $this->info('Completed generating inventory report');
        Log::info('End generating and emailing '.$duration.' Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').') at '. Carbon::now());
    }

    protected function generateReport($startdate, $enddate, Mailer $mailer, $duration = '') {
        //$merchant_id = $merchant->id;

        $dataArray = array();
        // run without filtering by channel id ('master' sheet)
        ini_set('memory_limit','-1');
        $dataArray['Master'] = $this->inventoryQuery($startdate, $enddate);

        // retrieve deleted products
        $dataArray['Deleted'] = $this->inventoryQuery($startdate, $enddate, true);

        // categories tab
        $dataArray['Categories'] = $this->categoryQuery();

        // retrieve products from deactivated merchants
        $dataArray['Deactivated Merchants'] = $this->inventoryQuery($startdate, $enddate, false, true);

        $this->info("generateReport inventory");
        // run query for each channel

        // generate stock end and upload to S3
        $csv = $this->storeStockEnd($dataArray['Master'], $duration, $enddate);
        $uploadedfile = Storage::disk('local')->get('reports/'.$csv['file']);
        $s3path = $this->inventoryStockEndDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$csv['file'];
        $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
        if($s3upload){
            Storage::delete($csv['file']);
        }
        $this->info("generateReport1 End");
        return $dataArray;
    }

    protected function generateReportChannel($startdate, $enddate, Mailer $mailer, $duration = '') {
        //$merchant_id = $merchant->id;
        // get list of channel ids
        $channels = Channel::select('id', 'name', 'issuing_company')
                    ->where('name','not like',$this->test_channels)
                    ->where('name','not like',$this->correction_channels)
                    ->where('channel_type_id','<>',$this->warehouse_channel_type)
                    ->get();

        $this->info("generateReport channel inventory");
        $dataArray = array();
        ini_set('memory_limit','-1');
        // run query for each channel

        //other sheet
        foreach($channels as $channel) {
          $result = $this->channelInventoryQuery($startdate, $enddate, $channel->id);
          // remove special chars and allow only 30 chars
          $sheetName = substr(preg_replace("/[^A-Za-z0-9_.@\-]/", '', $channel->name),0,25);  

          if (count($result) > 0) {
            $dataArray['All'][] = $result;
          }        
        } 
        $this->info("generateReport2 End "); 
        return $dataArray;
    }

    private function inventoryQuery($startDateTime, $endDateTime, $get_deleted=false, $get_deactivated=false,$timezone ="+08:00") {

        $query = 'SELECT sku.`sku_id` AS "System SKU",
                IF (brands.active = 1 , "Active", "Inactive") as "Brand Status",
                merchants.`name` AS "Merchant",
                IF (brands.`name` IS NOT NULL , brands.`name` , products.`brand`) AS Brand,
                sku.`hubwire_sku` AS "Hubwire SKU",
                sku.`sku_supplier_code` AS "Supplier SKU",
                convert_tz(product_procured.`receive_date`,"+00:00","'.$timezone.'") AS "Product Creation Date",
                ';
        if ($get_deleted) {
            $query .= 'products.`deleted_at` AS "Product Deletion Date", ';
        }

        $query .= 'products.`name` AS "Product Name",
                MAX(size.`option_value`) AS Size,
                MAX(colour.`option_value`) AS Colour,
                IF(available_stock_start.`quantity` IS NOT NULL, available_stock_start.`quantity`, 0)+
                IF(reserved_start.`quantity` IS NOT NULL, reserved_start.`quantity`, 0)+
                IF(unreceived_st_start.`quantity` IS NOT NULL, unreceived_st_start.`quantity`, 0) +
                IF(correction.`quantity` IS NOT NULL, correction.`quantity`, 0) AS "Stock In-hand Start",';
        /*  
            Breakdown for stock in hand
                IF(available_stock_start.`quantity` IS NOT NULL, available_stock_start.`quantity`, 0) AS "Stock In-hand Start",
                IF(reserved_start.`quantity` IS NOT NULL, reserved_start.`quantity`, 0) AS "Reserved Start",
                IF(unreceived_st_start.`quantity` IS NOT NULL, unreceived_st_start.`quantity`, 0) AS "Unreceived ST Start",';
        */
        $query .='IF(purchases.`inbound` IS NOT NULL, purchases.`inbound`, 0) AS "Inbound",
                IF(reject_items.`outbound` IS NOT NULL, reject_items.`outbound`, 0) AS "Outbound",
                IF(adjustments.`adjustment` IS NOT NULL, adjustments.`adjustment`, 0) AS "Adjustment",
                IF(completed_orders.sold IS NOT NULL, completed_orders.`sold`, 0) as "Shipped",
                IF(return_processed.return_qty IS NOT NULL, return_processed.return_qty, 0) AS "Returns Processed",
                IF(available_stock_end.`quantity` IS NOT NULL, available_stock_end.`quantity`, 0) +
                IF(reserved_end.`quantity` IS NOT NULL, reserved_end.`quantity`, 0) +
                IF(unreceived_st_end.`quantity` IS NOT NULL, unreceived_st_end.`quantity`, 0) AS "Stock In-hand End"';
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
                WHERE merchants.name not in ("'.$this->test_merchant.'") AND ';

        if ($get_deleted) {
            $query = $query . 'products.deleted_at IS NOT NULL AND products.deleted_at < "'.$endDateTime.'"';
        }
        else if($get_deactivated) {
            $query = $query . 'merchants.status = "Inactive"';   
        }
        else {
            $query = $query . '(merchants.deleted_at IS NULL OR merchants.deleted_at > "'.$endDateTime.'")
            AND merchants.status = "Active"
            AND (products.deleted_at IS NULL OR products.deleted_at > "'.$endDateTime.'")
            AND (sku.deleted_at IS NULL OR sku.deleted_at > "'.$endDateTime.'")
            AND (brands.deleted_at IS NULL OR brands.deleted_at > "'.$endDateTime.'")
            AND sku.`created_at` < "'.$endDateTime.'"';
        }

        $query .= ' GROUP BY sku.sku_id
                    ORDER BY merchants.name ASC, products.created_at ASC, hubwire_sku ASC';

        $inventory = DB::select(DB::raw($query));
        //Log::info("Master Inventory ".print_r($query,true));
        
        return $inventory;
    }
    private function channelInventoryQuery($startDateTime, $endDateTime, $channel_id=null, $timezone ="+08:00") {

        $query = 'SELECT sku.`sku_id` "System SKU",
                    IF (brands.active = 1 , "Active", "Inactive") as "Brand Status",
                    merchants.`name` as "Merchant",
                    channels.`name` as "Channel",
                    sku.`hubwire_sku` AS "Hubwire SKU",
                    sku.`sku_supplier_code` AS "Supplier SKU",
                    CONVERT_TZ(channel_sku.`created_at`,"+00:00","'.$timezone.'") AS "Channel SKU Creation Date",
                     products.`name` AS "Product Name",
                    IF (brands.`name` IS NOT NULL , brands.`name` , products.`brand`) AS Brand,
                    MAX(size.`option_value`) AS Size,
                    MAX(colour.`option_value`) AS Colour,
                    IF(wh.`channel_sku_price` IS NOT NULL , round(wh.channel_sku_price,2), 0.00) AS "Warehouse Retail price",
                    IF(stock_start.`channel_sku_quantity` IS NOT NULL, stock_start.`channel_sku_quantity`, "0") AS "Begining Available Stock",
                    IF(reject_items.`outbound` IS NOT NULL, reject_items.`outbound`, 0) AS "Outbound",
                    IF(adjustments.`adjustment` IS NOT NULL, adjustments.`adjustment`, 0) AS "Adjustment",
                    IF(completed_orders.sold IS NOT NULL, completed_orders.`sold`, 0) as "Shipped",
                    IF(return_processed.return_qty IS NOT NULL, return_processed.return_qty, 0) AS "Returns Processed",
                    IF(stock_end.`channel_sku_quantity` IS NOT NULL, stock_end.`channel_sku_quantity`, "0") AS "Ending Available Stock"
                FROM (select * from channel_sku where channel_id = '.$channel_id.') channel_sku
                LEFT JOIN sku ON sku.sku_id = channel_sku.sku_id
                LEFT JOIN channels ON channels.id = channel_sku.channel_id
                LEFT JOIN (SELECT sku_id , channel_sku_price FROM channel_sku LEFT JOIN channels on channels.id = channel_sku.channel_id where channels.`channel_type_id` = '.$this->warehouse_channel_type.' group by sku_id order By channel_sku_id desc) wh
                ON sku.sku_id = wh.sku_id
                LEFT JOIN sku_combinations ON sku_combinations.sku_id = sku.sku_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Colour") as colour ON sku_combinations.option_id = colour.option_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Size") as size ON sku_combinations.option_id = size.option_id
                LEFT JOIN products ON sku.product_id = products.id
                LEFT JOIN brands ON products.brand_id = brands.id
                LEFT JOIN merchants ON brands.merchant_id = merchants.id
                LEFT JOIN (
                    SELECT order_items.ref_id as channel_sku_id, sum(return_log.quantity) AS return_qty
                    FROM return_log
                    LEFT JOIN order_items on order_items.id  = return_log.order_item_id and order_items.ref_type = "ChannelSKU"
                    WHERE return_log.completed_at >= "'.$startDateTime.'"
                    AND return_log.completed_at < "'.$endDateTime.'"
                    AND return_log.quantity <> 0
                    AND order_items.status <> "Cancelled"
                    AND return_log.order_item_id <> 0
                    GROUP BY order_items.ref_id
                ) return_processed ON channel_sku.channel_sku_id = return_processed.channel_sku_id
                LEFT JOIN (
                    SELECT order_items.ref_id as channel_sku_id, SUM(order_items.original_quantity) AS sold
                    FROM order_items
                    LEFT JOIN orders ON orders.id = order_items.order_id
                    LEFT JOIN order_status_log ON order_status_log.to_status = "Completed" and order_status_log.order_id = orders.id
                    WHERE order_status_log.created_at >= "'.$startDateTime.'"
                    AND order_status_log.created_at <= "'.$endDateTime.'"
                    AND orders.status >= "'.Order::$completedStatus.'"
                    AND order_items.status in ("Verified","Returned")
                    AND orders.channel_id = '.$channel_id.'
                    AND order_status_log.user_id = 0
                    GROUP BY order_items.ref_id
                ) completed_orders ON channel_sku.channel_sku_id = completed_orders.channel_sku_id
                LEFT JOIN (
                    SELECT MAX(id) as id,channel_sku_id
                    FROM inventory_stock_cache
                    WHERE inventory_stock_cache.last_stock_updated_at <= "'.$startDateTime.'"
                    GROUP BY channel_sku_id
                ) stock_start_ids ON stock_start_ids.channel_sku_id = channel_sku.channel_sku_id
                LEFT JOIN inventory_stock_cache stock_start on stock_start_ids.id = stock_start.id
                LEFT JOIN(
                    SELECT MAX(id) as id,channel_sku_id
                    FROM inventory_stock_cache
                    WHERE inventory_stock_cache.last_stock_updated_at <= "'.$endDateTime.'"
                    GROUP BY channel_sku_id
                ) stock_end_ids ON stock_end_ids.channel_sku_id = channel_sku.channel_sku_id
                LEFT JOIN inventory_stock_cache stock_end on stock_end_ids.id = stock_end.id
                LEFT JOIN (
                    SELECT reject_log.sku_id,SUM(reject_log.quantity) AS outbound
                    FROM reject_log
                    WHERE reject_log.created_at >= "'.$startDateTime.'" AND reject_log.created_at < "'.$endDateTime.'"
                    AND outbound = 1 GROUP BY reject_log.sku_id
                ) reject_items ON sku.sku_id = reject_items.sku_id 
                LEFT JOIN (
                    SELECT reject_log.sku_id,SUM(reject_log.quantity) AS adjustment
                    FROM reject_log
                    WHERE reject_log.created_at >= "'.$startDateTime.'" AND reject_log.created_at < "'.$endDateTime.'"
                    AND outbound = 0 GROUP BY reject_log.sku_id
                ) adjustments ON sku.sku_id = adjustments.sku_id 
        ';

        $query = $query . 'WHERE merchants.name not in ("'.$this->test_merchant.'")
            AND merchants.status = "Active"
            AND (merchants.deleted_at IS NULL OR merchants.deleted_at > "'.$endDateTime.'")
            AND (products.deleted_at IS NULL OR products.deleted_at > "'.$endDateTime.'")
            AND (sku.deleted_at IS NULL OR sku.deleted_at > "'.$endDateTime.'")
            AND (brands.deleted_at IS NULL OR brands.deleted_at > "'.$endDateTime.'")
            AND channel_sku.created_at < "'.$endDateTime.'"';


        $query .= ' GROUP BY channel_sku.channel_sku_id
                    ORDER BY merchants.name ASC, products.created_at ASC, hubwire_sku ASC';
        //Log::info("Channel Inventory ".print_r($query,true));
        $inventory = DB::select(DB::raw($query));
        //\Log::info($query);
        return $inventory;

    }

    private function categoryQuery()
    {
        $data = DB::table('products')
                    ->select(DB::raw('categories.full_name, count(*) AS "count"'))
                    ->join('categories', 'products.category_id', '=', 'categories.id')
                    ->groupBy('products.category_id')
                    ->get();

        // split category parts into separate columns
        $categoryData = array();

        foreach ($data as $row) {
            $cat = explode('/', $row->full_name);
            $categoryData[] = ['Main Category' => isset($cat[0])? $cat[0] : '',
                                'Sub-category' => isset($cat[1])? $cat[1] : '',
                                'Sub sub-category' => isset($cat[2])? $cat[2] : '',
                                'Count' => $row->count
                            ];
        }

        return $categoryData;
    }

    protected function createExcel($excelData, $startdate, $enddate, $filename, $duration, $fileType){

        return Excel::create($filename, function($excel) use($excelData, $startdate, $enddate, $duration, $fileType){
            $time_start = microtime(true);

            foreach ($excelData as $sheetName => $sheetData) {
                
                $sheetName = substr(preg_replace("/[^A-Za-z0-9_.@\-]/", '', $sheetName),0,30);
                # code...
                $dataArray = array_chunk($sheetData, 3000);
                if (!is_null($dataArray) && !empty($dataArray)) {
                    $excel->sheet($sheetName, function($sheet) use($dataArray, $startdate, $enddate, $duration, $fileType) {
                        if ( $fileType == 'csv') {
                            foreach ($dataArray as $datas) {
                                $headers = array_keys((array)$datas[1][0]);
                                $endchar =chr(ord('A')+sizeof($headers));

                                $sheet->appendRow(array($duration . 'Channel Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString().' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString().')'));
                                
                                $sheet->appendRow( $headers );
                                foreach ($datas as $data) {
                                    $array = json_decode(json_encode($data), true);
                                    $sheet->rows($array);
                                }
                            }
                        }else {

                            $headers = array_keys((array)$dataArray[0][0]);
                            $endchar =chr(ord('A')+sizeof($headers));

                            $sheet->appendRow(array($duration . ' Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString().' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString().')'));
                            $sheet->appendRow( array('') );
                $sheet->appendRow( $headers );
                            $sheet->cells('A1:'.$endchar.'1', function($cells) {
                                $cells->setBackground('#205081');
                                $cells->setFontColor('#ffffff');
                                $cells->setFont(array(
                                    'size'       => '16',
                                    'bold'       =>  true
                                ));
                            });

                            foreach ($dataArray as $data) {
                                $array = json_decode(json_encode($data), true);
                                $sheet->rows($array);
                            }
                        }
                    });
                }
            }
            
            $excel->setActiveSheetIndex(0);
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start)/60;
            $this->info('<b>Chunk Execution Time of create '.$fileType.':</b> '.$execution_time.' Mins');
        })->store($fileType, storage_path('app/reports'));

    }

    protected function storeStockEnd($dataArray, $duration, $enddate)
    {
        $filename = 'inventory_stock_end_'.$duration.'_'.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd');
        return Excel::create($filename, function($excel) use($dataArray, $enddate){

            $excel->sheet('stock end', function($sheet) use($dataArray, $enddate) {

                $sheet->appendRow(['System SKU', 'Stock In-hand End']);
                foreach ($dataArray as $data) {
                    $array = json_decode(json_encode($data), true);
                    $sheet->appendRow(array($array['System SKU'], $array['Stock In-hand End'], $enddate));
                }
            });

        })->store('csv', storage_path('app/reports'));
    }
}
