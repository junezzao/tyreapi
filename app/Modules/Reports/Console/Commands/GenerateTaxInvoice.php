<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Admin\Order;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use Excel;
use App\Services\Mailer;
use Log;
use DB;
use Storage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

class GenerateTaxInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:TaxInvoice {startdate?} {enddate?}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Sales Report';

    protected $mailer;

    protected $reportsDirectory = 'reports';
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
        $startdate = ($this->argument('startdate')=='daily'||is_null($this->argument('startdate'))) ?Carbon::now()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'):$this->argument('startdate');
        $enddate = ($this->argument('enddate')=='daily'||is_null($this->argument('enddate'))) ?date('Y-m-d H:i', strtotime($startdate . ' +1 day')):$this->argument('enddate');dd($enddate);
        //$emails = $this->argument('emails');

        Log::info('Begin generation Sales Report at ' . Carbon::now());
        if (is_null($this->argument('enddate'))&&is_null($enddate)) {
            dd("Wrong Input... Spare between argument 1 and argumen 2.");
        }
        $this->info('Generating sales report from '. $startdate . ' to '. $enddate);
        $orders = $this->getDurationOrder($startdate,$enddate);
        $dataPass = $this->getOrderData($orders);
        $excelFile = $this->generateExcelFile($dataPass);
        $this->uploadExcel($excelFile,$enddate, $startdate);
        $this->info('Email have been send to angie@hubwire.com');
    }

    public function getDurationOrder($startdate, $enddate) {
        $date_range = [$startdate, $enddate];

        $orders = Order::leftJoin(

        \DB::raw("
            (select
                `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0
            ) `order_completed`

        "), 'order_completed.order_id', '=', 'orders.id')
        ->whereBetween('orders.shipped_date', $date_range)
        ->where('status', Order::$completedStatus)
        ->get();
        return $orders;
    }

    public function getOrderData($orders) {

        $saleMasterData = array();
        foreach ($orders as $order) {
            $tax_invoice_no = DB::table('order_invoice')->where('order_id','=',$order->id)->value('tax_invoice_no');//get the tax invoice no
            $items = $order->itemSKUs;
            $itemArr = array();

            foreach ($items as $item) {
                if ($item->isChargeable()) {
                    if($item->tax_inclusive == true) {
                        $soldAmount = $item->sold_price;
                        $soldAmountWithoutGst = $item->sold_price - $item->tax;
                    } else if($item->tax_inclusive == false) {
                        $soldAmount = $item->sold_price + $item->tax;
                        $soldAmountWithoutGst = $item->sold_price;
                    }
                    $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                    $channel_sku = ChannelSKU::find($item->ref_id);

                    $itemArr[] = array(
                        'sku_id'    => $item->ref->sku->sku_id,
                        'channel_id'    => $channel_sku->channel_id,
                        'unit_price'    => $item->unit_price,
                        'sale_price'    => ($item->sale_price == 0)?$item->unit_price:$item->sale_price,
                        'total_amount_paid'     => $soldAmount * $item->original_quantity,
                        'total_amount_paid_excl_gst'    => $soldAmountWithoutGst * $item->original_quantity,
                        'total_discount'    => $discount * $item->original_quantity,
                        'total_quantity' => $item->original_quantity
                    );
                }
            }

            foreach ($itemArr as $itemTotals) {
                $sku = SKU::find($itemTotals['sku_id']);
                $product = Product::with('brand', 'merchant')->find($sku->product_id);
                $channel = Channel::with('channel_type')->find($itemTotals['channel_id']);
                if(strcmp($product->merchant->name,$this->test_merchant)==0){
                    continue;
                }
                $get_issuing_companies = json_decode(json_encode(DB::table('issuing_companies')->where('id', $channel->issuing_company)->first()), true); 
                $gst_reg = $get_issuing_companies['gst_reg'];
                $get_tax = json_decode(json_encode(DB::table('order_items')->where('order_id', $order->id)->first()), true); 
                $tax_rate= $get_tax['tax_rate'];
                if($gst_reg == 1){
                    $reportData = [
                        'Merchant' => $product->merchant->name,
                        'Channel' => $channel->name,
                        'Channel Type' => $channel->channel_type->name,
                        'Third Party Order Date' => Carbon::createFromFormat('Y-m-d H:i:s', $order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y'),
                        'Order Completed Date' => Carbon::createFromFormat('Y-m-d H:i:s', $order->shipped_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y'),
                        'Tax Invoice Number' => $tax_invoice_no,
                        'Order No' => $order->id,
                        'Third Party Order No' => $order->tp_order_code,
                        //'Brand' => $product->getRelation('brand')->name,
                        //'Hubwire SKU' => $sku->hubwire_sku,
                        //'Supplier SKU' => $sku->sku_supplier_code,
                        //'Product Name' => $product->name,
                        //'Size' => $sku->size,
                        //'Color' => $sku->color,
                        //'Quantity' => $itemTotals['total_quantity'],
                        'Currency' => $order->currency,
                        'Listing Price (Excl. GST)'=> number_format($itemTotals['sale_price']/(1+$tax_rate), 2),
                        'Listing Price (Incl. GST)'=> number_format($itemTotals['sale_price'], 2),
                        'Total Sales (Excl. GST)' => number_format($itemTotals['total_amount_paid_excl_gst'], 2),
                        'HW Discounts (Excluding gst)' => number_format($itemTotals['total_discount'], 2), 
                        'GST Amount' => number_format(($itemTotals['total_amount_paid']-$itemTotals['total_amount_paid_excl_gst']), 2),
                        
                    ];
                }

                $saleMasterData[] = $reportData;
                
            }
        }
        
        return $saleMasterData;
    }

    public function generateExcelFile($dataPass) {
        $saleMasterData = $dataPass;
        $filename = 'tax_invoice-'.Carbon::now()->format('YmdHis'); // timestamp in UTC
        $excel = Excel::create($filename, function($excel) use($saleMasterData) {

            $excel->sheet('Master List', function($sheet) use($saleMasterData) {

                $sheet->fromArray($saleMasterData, null, 'A1', true);

                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array('Tax Invoice')
                        );

                $sheet->cells('A1:T1', function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('A3:T3', function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('H3:L'.(sizeof($saleMasterData)+3), function($cells) {
                    $cells->setAlignment('left');
                });
            });

            $excel->setActiveSheetIndex(0);
        })->store('xls', storage_path('app/reports'), true);
        return $excel;
    }

    public function uploadExcel($excel, $enddate, $startdate) {
        // move file to S3
        $uploadedfile = Storage::disk('local')->get('reports/'.$excel['file']);
        $s3path = $this->reportsDirectory.'/TaxInvoice/'.date('Y', strtotime($enddate)).'/'.date('m', strtotime($enddate)).'/'.$excel['file'];
        $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
        if($s3upload){
            Storage::disk('local')->delete('reports/'.$excel['file']);

            $email_data['url'] = env('AWS_S3_URL').$s3path;
            $email_data['email'] = 'angie@hubwire.com';
            $email_data['subject'] = 'Daily Tax Invoice('.date('Y-m-d', strtotime($startdate)).')';
            $email_data['report_type'] = 'Tax Invoice';
            $email_data['date'] = date('Y-m-d', strtotime($startdate));
            $this->mailer->scheduledTaxInvoice($email_data);
        }
        Log::info('End generating and emailing Tax Invoice ('.date('Y-m-d', strtotime($startdate)).') at '. Carbon::now());
    }

    #end
}
