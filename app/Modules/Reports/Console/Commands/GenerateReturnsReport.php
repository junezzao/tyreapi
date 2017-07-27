<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\Merchant;
use Excel;
use Log;
use Storage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use App\Services\Mailer;

class GenerateReturnsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generateReturnsReport {startdate} {enddate} {emails} {duration?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Returns Report ';

    protected $reportsDirectory = 'reports/returns';

    protected $mailer;

    private $test_merchant = "Test Merchant";
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
        $duration = ($this->argument('duration') ? $this->argument('duration') : '');
        $emails = $this->argument('emails');
        Log::info('Begin generation Returns Report at ' . Carbon::now());
        $this->info('Generating return report from '. $startdate . ' to '. $enddate);

        //$merchants = Merchant::all();

        $returnsData = array();
        $uploadedReports = array();

        // get all returns for the month
        $newReturns = ReturnLog::whereBetween('created_at', [$startdate, $enddate])->get(); // this includes cancelled items
        $pendingReturns = ReturnLog::where('created_at', '<', $startdate)->where('status', 'In Transit')->get();
        $completedReturns = ReturnLog::where('created_at', '<', $startdate)->whereBetween('completed_at', [$startdate, $enddate])->get();
        $newReturnsData = array();
        $newCancellationData = array();
        $pendingReturnsData = array();
        $completedReturnsData = array();

        if(count($newReturns) > 0){
            //$bar = $this->output->createProgressBar(count($newReturns));

            foreach ($newReturns as $return) {
                $sku = $return->item->ref->sku;
                $product = $sku->product;
                $merchant = $sku->merchant_id;
                $channel = $return->order->channel;
                $item = $return->item;
                if(strcmp($product->merchant->name,$this->test_merchant)==0){
                    continue;
                }
                if($item->tax_inclusive == true) {
                    $soldAmountWithoutGst = $item->sold_price - $item->tax;
                } else {
                    $soldAmountWithoutGst = $item->sold_price;
                }
                $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;

                if($return->item->isChargeable()){
                    $returnsData[$merchant][0][] =
                        [
                            'Channel' => $channel->name,
                            'Channel Type' => $channel->channel_type->name,
                            'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order No' => $return->order_id,
                            'Third Party Order No' => $return->order->tp_order_code,
                            'Brand' => $product->brand_name,
                            'Hubwire SKU' => $sku->hubwire_sku,
                            'Supplier SKU' => $sku->supplier_sku_code,
                            'Product Name' => $product->name,
                            'Size' => $sku->size,
                            'Color' => $sku->color,
                            'Quantity' => $return->quantity,
                            'Currency' => $return->order->currency,
                            'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                            'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                            'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                            'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                            'Discounts' => number_format($discount, 2), // sum of all the quantities
                            'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                            'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                            'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Return Reason' => $return->remark,
                            'Type' => $return->item->status,
                            'Status' => $return->status,
                            'Restock Date' => ($return->completed_at === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->completed_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Consignment Number' => $item['order']['consignment_no'],
                       ];

                    $newReturnsData[] =
                        [
                            'Merchant' => $product->merchant->name,
                            'Channel' => $channel->name,
                            'Channel Type' => $channel->channel_type->name,
                            'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order No' => $return->order_id,
                            'Third Party Order No' => $return->order->tp_order_code,
                            'Brand' => $product->brand_name,
                            'Hubwire SKU' => $sku->hubwire_sku,
                            'Supplier SKU' => $sku->supplier_sku_code,
                            'Product Name' => $product->name,
                            'Size' => $sku->size,
                            'Color' => $sku->color,
                            'Quantity' => $return->quantity,
                            'Currency' => $return->order->currency,
                            'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                            'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                            'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                            'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                            'Discounts' => number_format($discount, 2), // sum of all the quantities
                            'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                            'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                            'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Return Reason' => $return->remark,
                            'Type' => $return->item->status,
                            'Status' => $return->status,
                            'Restock Date' => ($return->completed_at === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->completed_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Consignment Number' => $item['order']['consignment_no'],
                       ];
                }else{
                    $returnsData[$merchant][1][] =
                        [
                            'Channel' => $channel->name,
                            'Channel Type' => $channel->channel_type->name,
                            'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order Cancelled Date' => ($return->order->cancelled_date === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->cancelled_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order No' => $return->order_id,
                            'Third Party Order No' => $return->order->tp_order_code,
                            'Brand' => $product->brand_name,
                            'Hubwire SKU' => $sku->hubwire_sku,
                            'Supplier SKU' => $sku->supplier_sku_code,
                            'Product Name' => $product->name,
                            'Size' => $sku->size,
                            'Color' => $sku->color,
                            'Quantity' => $return->quantity,
                            'Currency' => $return->order->currency,
                            'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                            'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                            'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                            'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                            'Discounts' => number_format($discount, 2), // sum of all the quantities
                            'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                            'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                            'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Return Reason' => $return->remark,
                            'Type' => $return->item->status,
                            'Status' => $return->status,
                            'Consignment Number' => $item['order']['consignment_no'],
                       ];
                    $newCancellationData[] =
                        [
                            'Merchant' => $product->merchant->name,
                            'Channel' => $channel->name,
                            'Channel Type' => $channel->channel_type->name,
                            'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order Cancelled Date' => ($return->order->cancelled_date === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->cancelled_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Order No' => $return->order_id,
                            'Third Party Order No' => $return->order->tp_order_code,
                            'Brand' => $product->brand_name,
                            'Hubwire SKU' => $sku->hubwire_sku,
                            'Supplier SKU' => $sku->supplier_sku_code,
                            'Product Name' => $product->name,
                            'Size' => $sku->size,
                            'Color' => $sku->color,
                            'Quantity' => $return->quantity,
                            'Currency' => $return->order->currency,
                            'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                            'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                            'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                            'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                            'Discounts' => number_format($discount, 2), // sum of all the quantities
                            'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                            'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                            'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                            'Return Reason' => $return->remark,
                            'Type' => $return->item->status,
                            'Status' => $return->status,
                            'Consignment Number' => $item['order']['consignment_no'],
                       ];
                }

            }

        }
        //$this->info('Completed compiling new returns. Total records : '. count($newReturnsData));

        // get all returns that are still in transit from the previous months
        if(count($pendingReturns) > 0){

            foreach ($pendingReturns as $return) {

                $sku = $return->item->ref->sku;
                $product = $sku->product;
                $merchant = $sku->merchant_id;
                $channel = $return->order->channel;
                $item = $return->item;
                if(strcmp($product->merchant->name,$this->test_merchant)==0){
                    continue;
                }

                if($item->tax_inclusive == true) {
                    $soldAmountWithoutGst = $item->sold_price - $item->tax;
                } else {
                    $soldAmountWithoutGst = $item->sold_price;
                }
                $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                
                $returnsData[$merchant][2][] = [
                    'Channel' => $channel->name,
                    'Channel Type' => $channel->channel_type->name,
                    'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order No' => $return->order_id,
                    'Third Party Order No' => $return->order->tp_order_code,
                    'Brand' => $product->brand_name,
                    'Hubwire SKU' => $sku->hubwire_sku,
                    'Supplier SKU' => $sku->supplier_sku_code,
                    'Product Name' => $product->name,
                    'Size' => $sku->size,
                    'Color' => $sku->color,
                    'Quantity' => $return->quantity,
                    'Currency' => $return->order->currency,
                    'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                    'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                    'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                    'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                    'Discounts' => number_format($discount, 2), // sum of all the quantities
                    'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                    'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                    'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Return Reason' => $return->remark,
                    'Type' => $return->item->status,
                    'Status' => $return->status,
                    'Consignment Number' => $item['order']['consignment_no'],
                   ];


                $pendingReturnsData[] =
                [
                    'Merchant' => $product->merchant->name,
                    'Channel' => $channel->name,
                    'Channel Type' => $channel->channel_type->name,
                    'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order No' => $return->order_id,
                    'Third Party Order No' => $return->order->tp_order_code,
                    'Brand' => $product->brand_name,
                    'Hubwire SKU' => $sku->hubwire_sku,
                    'Supplier SKU' => $sku->supplier_sku_code,
                    'Product Name' => $product->name,
                    'Size' => $sku->size,
                    'Color' => $sku->color,
                    'Quantity' => $return->quantity,
                    'Currency' => $return->order->currency,
                    'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                    'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                    'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                    'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                    'Discounts' => number_format($discount, 2), // sum of all the quantities
                    'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                    'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                    'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Return Reason' => $return->remark,
                    'Type' => $return->item->status,
                    'Status' => $return->status,
                    'Consignment Number' => $item['order']['consignment_no'],
               ];
            }
        }
        //$this->info('Completed compiling pending returns. Total records : '. count($pendingReturnsData));

        if(count($completedReturns) > 0){
            foreach ($completedReturns as $return) {

                $sku = $return->item->ref->sku;
                $product = $sku->product;
                $merchant = $sku->merchant_id;
                $channel = $return->order->channel;
                $item = $return->item;
                if(strcmp($product->merchant->name,$this->test_merchant)==0){
                    continue;
                }
                $returnsData[$merchant][3][] =
                [
                    'Channel' => $channel->name,
                    'Channel Type' => $channel->channel_type->name,
                    'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order No' => $return->order_id,
                    'Third Party Order No' => $return->order->tp_order_code,
                    'Brand' => $product->brand_name,
                    'Hubwire SKU' => $sku->hubwire_sku,
                    'Supplier SKU' => $sku->supplier_sku_code,
                    'Product Name' => $product->name,
                    'Size' => $sku->size,
                    'Color' => $sku->color,
                    'Quantity' => $return->quantity,
                    'Currency' => $return->order->currency,
                    'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                    'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                    'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                    'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                    'Discounts' => number_format($discount, 2), // sum of all the quantities
                    'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                    'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                    'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Return Reason' => $return->remark,
                    'Type' => $return->item->status,
                    'Status' => $return->status,
                    'Restock Date' => ($return->completed_at === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->completed_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Consignment Number' => $item['order']['consignment_no'],
                ];


                $completedReturnsData[] =
                [
                    'Merchant' => $product->merchant->name,
                    'Channel' => $channel->name,
                    'Channel Type' => $channel->channel_type->name,
                    'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order Completed Date' => ($return->order->orderDate($return->order_id) === false) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->order->orderDate($return->order_id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order No' => $return->order_id,
                    'Third Party Order No' => $return->order->tp_order_code,
                    'Brand' => $product->brand_name,
                    'Hubwire SKU' => $sku->hubwire_sku,
                    'Supplier SKU' => $sku->supplier_sku_code,
                    'Product Name' => $product->name,
                    'Size' => $sku->size,
                    'Color' => $sku->color,
                    'Quantity' => $return->quantity,
                    'Currency' => $return->order->currency,
                    'Retail Price (Incl. GST)' => number_format($item->unit_price, 2), // price per unit
                    'Retail Price (Excl. GST)' => number_format($item->unit_price/1.06, 2), 
                    'Listing Price (Incl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price, 2):number_format($item->sale_price, 2),
                    'Listing Price (Excl. GST)'=> ($item->sale_price == 0)?number_format($item->unit_price/1.06, 2):number_format($item->sale_price/1.06, 2),
                    'Discounts' => number_format($discount, 2), // sum of all the quantities
                    'Total Sales (Incl. GST)' => number_format($soldAmountWithoutGst*1.06, 2), 
                    'Total Sales (Excl. GST)' => number_format($soldAmountWithoutGst, 2), //(amount paid by customer exclusive GST)
                    'Return Date' => Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Return Reason' => $return->remark,
                    'Type' => $return->item->status,
                    'Status' => $return->status,
                    'Restock Date' => ($return->completed_at === null) ? '':Carbon::createFromFormat('Y-m-d H:i:s', $return->completed_at)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Consignment Number' => $item['order']['consignment_no'],
                ];
            }
        }
        //$this->info('Completed compiling completed returns. Total records : '. count($completedReturnsData));
        foreach($returnsData as $key => $value){
            $merchantData = $this->fillEmpty($value);
            ksort($merchantData);
            $merchant = Merchant::find($key);

            $filename = $merchant->slug.'_returns_report_'.$startdate->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$enddate->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('YmdHis'); // timestamp in UTC
            $excel = Excel::create($filename, function($excel) use($merchant, $merchantData, $startdate, $enddate) {

                foreach ($merchantData as $k => $data) {
                    $sheetName = $this->getSheetName($k);

                    $excel->sheet($sheetName, function($sheet) use($merchant, $data, $startdate, $enddate) {

                        $sheet->fromArray($data, null, 'A1', true);
                        $sheet->prependRow( array('') );
                        $sheet->prependRow(
                                    array($merchant->slug.' Returns Report ('.$startdate->toDateString() .' - '.$enddate->toDateString() .')')
                                );

                        $sheet->cells('A1:P1', function($cells) {
                            $cells->setBackground('#205081');
                            $cells->setFontColor('#ffffff');
                            $cells->setFont(array(
                                'size'       => '16',
                                'bold'       =>  true
                            ));
                        });
                    });
                }
                $excel->setActiveSheetIndex(0);
            })->store('xls', storage_path('app/reports'), true);

            // upload to S3 and email
            $uploadedfile = Storage::disk('local')->get('reports/'.$excel['file']);
            $s3path = $this->reportsDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$excel['file'];
            $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
            if($s3upload){
                Storage::disk('local')->delete('reports/'.$excel['file']);

                $uploadedReports[$merchant->name] = env('AWS_S3_URL').$s3path;
                /*
                * Disable email to merchant for now. Consider to use a different email template
                $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
                $email_data['url'] = env('AWS_S3_URL').$s3path;
                $email_data['email'] = $merchant->email;
                $email_data['subject'] = $duration . ' Returns Report ('.$startdate->format('Y-m-d').' - '.$enddate->format('Y-m-d').')';
                $email_data['report_type'] = 'Returns';
                $email_data['duration'] = $duration;
                $email_data['startdate'] = $startdate->format('Y-m-d');
                $email_data['enddate'] = $enddate->format('Y-m-d');
                $this->mailer->scheduledReport($email_data);*/
            }

        }

        // generate excel file
        $filename = 'summary_returns_report_'.$startdate->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$enddate->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('YmdHis'); // timestamp in UTC

        $excel = Excel::create($filename, function($excel) use($newReturnsData, $newCancellationData,$pendingReturnsData, $completedReturnsData, $startdate, $enddate) {

            $excel->sheet('New Returns List', function($sheet) use($newReturnsData, $startdate, $enddate) {

                $sheet->fromArray($newReturnsData, null, 'A1', true);
                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array('Returns Report ('.$startdate->toDateString() .' - '.$enddate->toDateString() .')')
                        );

                $sheet->cells('A1:AB1', function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('A3:AB3', function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });
                $sheet->setColumnFormat(array( 'AA' => 'text' ));

            });
            $excel->sheet('New Cancelled Returns List', function($sheet) use($newCancellationData, $startdate, $enddate) {

                $sheet->fromArray($newCancellationData, null, 'A1', true);
                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array('Returns Report ('.$startdate->toDateString() .' - '.$enddate->toDateString() .')')
                        );

                $sheet->cells('A1:AA1', function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('A3:AA3', function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });
                $sheet->setColumnFormat(array( 'Z' => 'text' ));

            });

            $excel->sheet('Pending Returns List', function($sheet) use($pendingReturnsData, $startdate, $enddate){

                $sheet->fromArray($pendingReturnsData, null, 'A1', true);
                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array('Returns Report ('.$startdate->toDateString() .' - '.$enddate->toDateString() .')')
                        );

                $sheet->cells('A1:AA1', function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('A3:AA3', function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });
                $sheet->setColumnFormat(array( 'Z' => 'text' ));
            });

            $excel->sheet('Completed Returns List', function($sheet) use($completedReturnsData, $startdate, $enddate){

                $sheet->fromArray($completedReturnsData, null, 'A1', true);
                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array('Returns Report ('.$startdate->toDateString() .' - '.$enddate->toDateString() .')')
                        );

                $sheet->cells('A1:AB1', function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('A3:AB3', function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });
                $sheet->setColumnFormat(array( 'AA' => 'text' ));
            });
            $excel->setActiveSheetIndex(0);
        })->store('xls', storage_path('app/reports'), true);

        // upload to S3 and email
        $uploadedfile = Storage::disk('local')->get('reports/'.$excel['file']);
        $s3path = $this->reportsDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$excel['file'];
        $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
        if($s3upload){
            Storage::disk('local')->delete('reports/'.$excel['file']);

            $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
            $email_data['url'] = env('AWS_S3_URL').$s3path;
            $email_data['email'] = $emails;
            $email_data['subject'] = $duration . ' Returns Report ('.$startdate->format('Y-m-d').' - '.$enddate->format('Y-m-d').')';
            $email_data['report_type'] = 'Returns';
            $email_data['duration'] = $duration;
            $email_data['startdate'] = $startdate->format('Y-m-d');
            $email_data['enddate'] = $enddate->format('Y-m-d');
            $email_data['report_list'] = $uploadedReports;
            $this->mailer->scheduledReport($email_data);
        }

        Log::info('End generating and emailing '.$duration.' Returns Report ('.$startdate->format('Y-m-d').' - '.$enddate->format('Y-m-d').') at '. Carbon::now());
    }

    private function fillEmpty($merchantData)
    {
        if(!array_key_exists(0, $merchantData)) {
            $merchantData[0] = ['No data to display.'];
        }
        if(!array_key_exists(1, $merchantData)) {
            $merchantData[1] = ['No data to display.'];
        }
        if(!array_key_exists(2, $merchantData)) {
            $merchantData[2] = ['No data to display.'];
        }
        if(!array_key_exists(3, $merchantData)) {
            $merchantData[3] = ['No data to display.'];
        }

        return $merchantData;
    }

    private function getSheetName($key)
    {
        switch ($key) {
            case 0:
                return 'New Returns List';
                break;
            case 1:
                return 'New Cancelled Returns List';
                break;
            case 2:
                return 'Pending Returns List';
                break;
            case 3:
                return 'Completed Returns List';
                break;
            default:
                break;
        }
    }
}
