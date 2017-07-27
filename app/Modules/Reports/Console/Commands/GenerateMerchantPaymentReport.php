<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Models\Admin\Contract;
use App\Models\Admin\Fee;
use App\Models\Admin\Brand;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\ThirdPartyReport;
use App\Models\Admin\ThirdPartyReportLog;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use DB;
use Storage;
use Log;
use Carbon\Carbon;
use Excel;
use App\Services\Mailer;
use PHPExcel_Worksheet_Drawing;
use PHPExcel_Style_Border;
use ZipArchive;

class GenerateMerchantPaymentReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    //example : php artisan reports:generateMerchantPaymentReport "2016-10-01 16:00"
    protected $signature = 'reports:generateMerchantPaymentReport {date?} {emails?} {--merchant= : Generate report for a specific merchant.} {--channelType= : Generate report for a specific channel type (online | retail | all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Merchant Payment Report';

    private $warehouse_channel_type = 12;
    private $test_merchant = "Test Merchant";
    private $test_channels = "%[Development Client]%";
    private $correction_channels = "%Correction%";

    protected $reportsDirectory = 'reports/payment_report';
    
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
        $date = $this->argument('date');
        $date = ( $date ? Carbon::createFromFormat('Y-m-d H:i', $date) : Carbon::now());
        $date_kl = $date->copy()->setTimezone('Asia/Kuala_Lumpur');
        $startdate_kl = $date_kl->copy()->startOfMonth();
        $enddate_kl = $date_kl->copy()->endOfMonth();
        $startdate = $startdate_kl->copy()->setTimezone('UTC');
        $enddate = $enddate_kl->copy()->setTimezone('UTC');
         
        $emails = ['reports@hubwire.com', 'angie@hubwire.com'];
        if(!empty($this->argument('emails'))) {
            array_push($emails, $this->argument('emails'));
        }
        
        $reportdate = $enddate->copy()->subMonthNoOverflow()->setTimezone('Asia/Kuala_Lumpur');
        $duration = 'Monthly';

        $inputMerchant = ($this->option('merchant') != '' ? explode(',', $this->option('merchant')) : array());
        $inputChannelType = ($this->option('channelType') != '' ? $this->option('channelType') : '');
        if ($inputChannelType == 'retail') {
            $channelTypes = ChannelType::where('name', '=', 'Shopify POS')->get()->pluck('id');
        }elseif ($inputChannelType == 'online') {
            $channelTypes = ChannelType::whereNotIn('name', ['Shopify POS', 'Warehouse'])->get()->pluck('id');
        }elseif ($inputChannelType == 'all' || $inputChannelType == '') {
            $channelTypes = ChannelType::whereNotIn('name', ['Warehouse'])->get()->pluck('id');
        }

        \Log::info('Generating payment report for payment date from '. $startdate . ' to '. $enddate .' for payment report month '. $reportdate->format('M Y'));
        $this->info('Generating payment report for payment date from '. $startdate . ' to '. $enddate .' for payment report month '. $reportdate->format('M Y'));

        // get all active merchants not selling on Fabspy channels
        $merchants = Merchant::isActive()->whereHas(
            'channels', function ($query) {
                $query->whereNotIn('channels.id', [13, 50, 72]);
            }
        )
        ->with('channels')
        ->get();

        if (empty($inputMerchant)) {
            $merchants = Merchant::isActive()->get();
        }else{
            $merchants = Merchant::isActive()->whereIn('id', $inputMerchant)->get();
        }
        
        $files = array();
        $zip = new ZipArchive;
        $zipFileName = '';
        $specialNameMerchant = null;
        if (!empty($inputMerchant)) {
            foreach($merchants as $merchant)
            {
                $specialNameMerchant .= $merchant->slug;
                $specialNameMerchant .= '_';
            }
        }
        $specialNameChannel =  (empty($inputChannelType) && $inputChannelType != 'all') ? '' : $inputChannelType;
        $specialName = strtoupper(preg_replace('/\s+/', '', $specialNameMerchant.$specialNameChannel));
        if (!empty($specialName)) {
            $zipFileName = 'merchant_payment_report_'.$specialName.'_'.strtoupper($reportdate->format('MY')).'_'.Carbon::now()->format('Ymd').'.zip';
        }elseif (empty($specialName)) {
            $zipFileName = 'merchant_payment_report_'.strtoupper($reportdate->format('MY')).'_'.Carbon::now()->format('Ymd').'.zip';
        }

        $create = $zip->open(storage_path('app/reports') . '/' . $zipFileName, ZipArchive::CREATE);
        
        $i=0;
        foreach($merchants as $merchant)
        {
            // if($i > 2) break;
            // filename
            $gst = (!empty($merchant->gst_reg_no))?true : false;
            $resultSet = $this->generateReport($merchant->id, $startdate, $enddate, $this->mailer, $duration, $gst, $channelTypes);
            $filename = $reportdate->format('ym').'-FMHW-'.strtoupper($merchant->slug).'-Payment Report';
            $fileType = 'xls';
            $excel_start = microtime(true);
            // timestamp in UTC
            // create excel file
            $excel = $this->createExcel($merchant, $resultSet, $startdate, $enddate, $filename, $duration, $fileType, $reportdate);
         
            $file = $filename.'.'.$fileType;
            $files[] = $file; 
            if($create)
            {
                $zip->addFile(storage_path('app/reports').'/'.$file,$file);
            }

            $excel_end = microtime(true);
            $excel_total_exec_time = ($excel_end - $excel_start)/60;

            $this->info('<b>Excel('.$fileType.') Execution Time :</b> '.$excel_total_exec_time.' Mins');
            $i++;            
        }
      
        $zip->close();
        
        
        if($create)
        {
            foreach($files as $file)
            {
                Storage::delete('reports/'.$file);
            }
             // move file to S3
            $uploadedfile = Storage::disk('local')->get('reports/'.$zipFileName);
            $s3path = $this->reportsDirectory.'/'.$reportdate->format('Y').'/'.$reportdate->format('m').'/'.$zipFileName;
            $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
            
            if($s3upload){
                $this->info('Zip Uploaded!');
                Storage::delete('reports/'.$zipFileName);

                $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
                $email_data['url'] = env('AWS_S3_URL').$s3path;
                $email_data['email']['to'] = $emails;
                if (!empty($specialName)) {
                    $email_data['subject'] = $duration . ' Merchant Payment Report for '.$specialName.' Sales ('.$reportdate->copy()->startOfMonth()->format('Y-m-d').' - '.$reportdate->copy()->endOfMonth()->format('Y-m-d').')';
                }elseif (empty($specialName)) {
                    $email_data['subject'] = $duration . ' Merchant Payment Report ('.$reportdate->copy()->startOfMonth()->format('Y-m-d').' - '.$reportdate->copy()->endOfMonth()->format('Y-m-d').')';
                }
                $email_data['report_type'] = 'Payment';
                $email_data['duration'] = $duration;
                $email_data['startdate'] = $reportdate->copy()->startOfMonth()->format('Y-m-d');
                $email_data['enddate'] = $reportdate->copy()->endOfMonth()->format('Y-m-d');
                $this->mailer->scheduledReport($email_data);
            }
        }
        
        $this->info('Completed generating report');
        Log::info('End generating and emailing '.$duration.' Merchant Payment Report ('.$reportdate->format('MY').') at '. Carbon::now());
    }

    protected function generateReport($merchant_id, $startdate, $enddate, Mailer $mailer, $duration = '', $gst, $channelTypes) {
        $dataArray = array();
        $summaries = array();
        
        ini_set('memory_limit','-1');

        /**
        ** sale Report
        */
        $sale = $this->saleQuery($merchant_id, $startdate, $enddate, $gst, $channelTypes);
        $sale['recon']['headers'] = [
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
            '3rd Party Commission Fees',
            '3rd Party Shipping Fees',
            '3rd Party Payment Gateway Fees',
            'Total 3rd Party Platform Fees',
            'FM Hubwire Fees (Exc GST)'
        ];
        $dataArray['RECON']['tables']['Payment - Completed Orders'] = $sale['recon'];

        /*
        ** Returns Report
        */

        $returns = $this->returnsQuery($merchant_id, $startdate, $enddate, $gst, $channelTypes);
        $returns['recon']['headers'] = [
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
            '3rd Party Commission Fees',
            '3rd Party Shipping Fees',
            '3rd Party Payment Gateway Fees',
            'Total 3rd Party Platform Fees',
            'FM Hubwire Fees (Exc GST)'
        ];
        $dataArray['RECON']['tables']['Return Orders'] = $returns['recon'];
        
        /*
        ** Pending Report
        */

        $pending = $this->pendingQuery($merchant_id, $startdate, $enddate, $gst, $channelTypes);
        $pending['recon']['headers'] = [
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
            '3rd Party Commission Fees',
            '3rd Party Shipping Fees',
            '3rd Party Payment Gateway Fees',
            'Total 3rd Party Platform Fees',
            'FM Hubwire Fees (Exc GST)'
        ];
        $dataArray['RECON']['tables']['Pending Orders'] = $pending['recon'];
        
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
            '3rd Party Commission fees',
            '3rd Party Shipping Fees',
            '3rd Party Payment Gateway Fees',
            'Total 3rd Party Platform Fees',
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
        $summary = $sale['summary'];
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

        $dataArray['RECON']['tables'][] = [
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
                '-',
                '-',
                $data_summary->first()['Total Sales (Exc GST)'] - $data_summary->last()['Total Sales (Exc GST)'] ,
                $data_summary->first()['3rd Party Commission Fees'] - $data_summary->last()['3rd Party Commission Fees'] ,
                $data_summary->first()['3rd Party Shipping Fees'] - $data_summary->last()['3rd Party Shipping Fees'] ,
                $data_summary->first()['3rd Party Payment Gateway Fees'] - $data_summary->last()['3rd Party Payment Gateway Fees'] ,
                $data_summary->first()['Total 3rd Party Platform Fees'] - $data_summary->last()['Total 3rd Party Platform Fees'] ,
                $data_summary->first()['FM Hubwire Fees (Exc GST)'] - $data_summary->last()['FM Hubwire Fees (Exc GST)']
            ],
            'pending' => [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Pending',
                $pending['summary']['Quantity'],
                '-',
                '-',
                $pending['summary']['Total Sales (Exc GST)'],
                $pending['summary']['3rd Party Commission Fees'],
                $pending['summary']['3rd Party Shipping Fees'],
                $pending['summary']['3rd Party Payment Gateway Fees'],
                $pending['summary']['Total 3rd Party Platform Fees'],
                $pending['summary']['FM Hubwire Fees (Exc GST)'],
            ],
        ];

        /**
        ** Payment Report
        */
        $payment = $this->paymentQuery($merchant_id, $sale['orders'], $returns['orders'], $gst, $startdate, $enddate);
        $payment['headers'] = [
            'Channel',
            'Total Sales (Exc GST)',
            'Return',
            'Total Sales Nett Returns',
            'FM Hubwire Fees',
            '3rd Party Commission Fees',
            '3rd Party Shipping Fees',
            '3rd Party Payment Gateway Fees',
            'Total 3rd Party Fees',
            'Balance to Brands',
        ];
        $dataArray['Payment Report']['tables']['data'] = $payment;
        
        return $dataArray;
    }

    private function saleQuery($merchant_id, $startDateTime, $endDateTime, $gst, $channelTypes,  $timezone ="+08:00") {
        $response = array();
        $date_range = [$startDateTime, $endDateTime];
       
        $items = OrderItem::chargeable()->leftJoin(
        \DB::raw("
            (select
                `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0 group by order_id
            ) `order_completed`

        "), 'order_completed.order_id', '=', 'order_items.order_id')
        ->select('order_items.*', 'third_party_report.channel_fees', 'third_party_report.channel_shipping_fees', 'third_party_report.channel_payment_gateway_fees', 'third_party_report.id as tp_id', 'orders.channel_id','third_party_report.sale_price as tp_sale_price')
        ->leftJoin('third_party_report','third_party_report.order_item_id','=','order_items.id')
        ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
        ->where('order_items.merchant_id', $merchant_id)//$merchant_id
        ->where('third_party_report.payment_date', '<=', $endDateTime) 
        ->where('third_party_report.item_status', \DB::raw('order_items.status'))
        ->where('third_party_report.status', 'Verified')
        ->where('third_party_report.paid_status', 1)
        ->whereIn('third_party_report.channel_type_id', $channelTypes)
        ->get();

        $reportData = array();
        $orders = array();
        $totalQuantity = 0;
        //$totalUnitPrice = 0;
        $totalSales = 0;
        $totalChannelFee = 0;
        $totalHwFee = 0;//dd($items);
        $totalChannelCommissionsFee = 0;
        $totalChannelShippingFee = 0;
        $totalChannelPaymentGatewayFee = 0;
        
        foreach($items as $item) {
            $check = true;
            $tpLogChannelFee = '';

            if ($item->status != 'Verified') {
                $tpLog = ThirdPartyReportLog::where('tp_report_id', '=', $item->tp_id)->whereBetween('created_at', $date_range)->where('new_value', 'Returned')->get();
                if (empty($tpLog)) {
                    $check = false;
                } else {
                    $tpLogChannelFee = ThirdPartyReportLog::where('tp_report_id', '=', $item->tp_id)->whereBetween('created_at', $date_range)->where('field', 'third_party_report.channel_fees')->first();
                    $tpLogCheckComplete = ThirdPartyReportLog::where('tp_report_id', '=', $item->tp_id)->whereNotBetween('created_at', $date_range)->where('new_value', 'Completed')->get();
                    if (empty($tpLogCheckComplete)) {
                        $check = false;
                    }
                }
            }
           
            
            if ($check) {
                $order = $item->order;
                $channel = $order->channel;
                $channelShippingFee = ($channel->channel_detail->use_shipping_rate == true ? $item->merchant_shipping_fee : $item->channel_shipping_fees);
                $unit_price     =   $item->unit_price/1.06;
                $using_price    =   ($channel->channel_detail->sale_amount) ? $item->tp_sale_price : $item->sold_price;
                $total_sales    =   ($channel->channel_detail->money_flow == 'Merchant') ? 0.00 : (($using_price>=0?$using_price:$item->unit_price)/1.06)*$item->original_quantity;
                $totalQuantity  +=  $item->original_quantity;
                $totalSales     +=  $total_sales;
                $channelFee = abs($item->channel_fees) + abs($channelShippingFee) + abs($item->channel_payment_gateway_fees);
                $totalChannelCommissionsFee +=  round((!empty($tpLogChannelFee) ? $tpLogChannelFee->old_value : abs($item->channel_fees)) / 1.06, 2);
                $totalChannelShippingFee += round(abs($channelShippingFee) / 1.06, 2);
                $totalChannelPaymentGatewayFee += round(abs($item->channel_payment_gateway_fees) / 1.06, 2);
                $totalChannelFee +=  round($channelFee / 1.06, 2);
               // $totalHwFee     +=  $item->hw_fee / 1.06;

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
                    'Discounts'                 => round(($using_price > 0 ? ($item->unit_price - $using_price)/$item->unit_price*100 : 0)).'%',
                    'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                    '3rd Party Commission Fees'=> number_format(((!empty($tpLogChannelFee) ? $tpLogChannelFee->old_value/1.06 : abs($item->channel_fees))/1.06),2,'.',''),
                    '3rd Party Shipping Fees'   => number_format(( abs($channelShippingFee)/1.06),2,'.',''),
                    '3rd Party Payment Gateway Fees'=> number_format((abs($item->channel_payment_gateway_fees)/1.06),2,'.',''),
                    'Total 3rd Party Platform Fees'   => number_format(($channelFee/1.06),2,'.',''),
                    'FM Hubwire Fees (Exc GST)' => '0.00',// number_format($item->hw_fee / 1.06,2,'.',''),
                ];
                $orders[$item->id]['id']           = $order->id;
                $orders[$item->id]['channel']      = $channel->name;
                $orders[$item->id]['sale']         = $total_sales;
                $orders[$item->id]['channelCommissionsFee']= number_format(((!empty($tpLogChannelFee) ? $tpLogChannelFee->old_value/1.06 : abs($item->channel_fees))/1.06),2,'.','');
                $orders[$item->id]['channelShippingFee']= number_format(( abs($channelShippingFee)/1.06),2,'.','');
                $orders[$item->id]['channelPaymentGatewayFee']= number_format((abs($item->channel_payment_gateway_fees)/1.06),2,'.','');
                $orders[$item->id]['channelFee']   = number_format(($channelFee/1.06),2,'.','');
                $orders[$item->id]['hubwireFee']   = number_format($item->hw_fee,2,'.','');
            }
            
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
            'TOTAL'                     => 'TOTAL',
            'Quantity'                  =>  $totalQuantity,
            'Unit Price (Exc GST)'      =>  '-',
            'Discounts'                 =>  '-',
            'Total Sales (Exc GST)'     =>  number_format($totalSales,2,'.',''),
            '3rd Party Commission Fees'=>  number_format($totalChannelCommissionsFee,2,'.',''),
            '3rd Party Shipping Fees'   =>  number_format($totalChannelShippingFee,2,'.',''),
            '3rd Party Payment Gateway Fees'=>  number_format($totalChannelPaymentGatewayFee,2,'.',''),
            'Total 3rd Party Platform Fees'   =>  number_format($totalChannelFee,2,'.',''),
            'FM Hubwire Fees (Exc GST)' =>  '0.00'//number_format($totalHwFee,2,'.','')
        ];
        $response['recon']['data'] = $reportData;
        $response['orders']= $orders;

        //dd($response['recon']);
        return $response;
    }

    private function returnsQuery($merchant_id, $startDateTime, $endDateTime, $gst, $channelTypes, $timezone ="+08:00") {
        
        $response = array();
        $date_range = [$startDateTime, $endDateTime];
        
        $returns = OrderItem::chargeable()->leftJoin(
            \DB::raw("
                (select
                    `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
                from `order_status_log`
                where `order_status_log`.`to_status` = 'Completed'
                and `order_status_log`.`user_id` = 0 group by order_id
                ) `order_completed`

            "), 'order_completed.order_id', '=', 'order_items.order_id')
            ->select('order_items.*', 'third_party_report.channel_fees', 'third_party_report.channel_shipping_fees', 'third_party_report.channel_payment_gateway_fees', 'third_party_report.id as tp_id', 'orders.channel_id','third_party_report.sale_price as tp_sale_price')
            ->leftJoin('third_party_report','third_party_report.order_item_id','=','order_items.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.merchant_id', $merchant_id)//$merchant_id
            ->where('third_party_report.payment_date', '<=', $endDateTime) 
            ->where('third_party_report.item_status', \DB::raw('order_items.status'))
            ->where('third_party_report.status', 'Verified')
            ->where('third_party_report.paid_status', 1)
            ->whereIn('third_party_report.channel_type_id', $channelTypes)
            ->get();

        $reportData = array();
        $orders = array();
        $totalQuantity = 0;
        //$totalUnitPrice = 0;
        $totalSales = 0;
        $totalChannelFee = 0;
        $totalHwFee = 0;
        $totalChannelCommissionsFee = 0;
        $totalChannelShippingFee = 0;
        $totalChannelPaymentGatewayFee = 0;

        foreach($returns as $return) {
	       $check = false;
            if ($return->status == 'Returned') {
                $tpLog = ThirdPartyReportLog::where('tp_report_id', '=', $return->tp_id)->whereBetween('created_at', $date_range)->where('new_value', 'Returned')->get();
                if (!empty($tpLog)) {
                    $check = true;
                }
            }
            //dd(json_decode($return, true));
            if ($check) {
                $order          =   $return->order;
                $channel        =   $order->channel;
                $channelCommissionFee = abs($return['channel_fees']);
                $channelShippingFee = ($channel->channel_detail->use_shipping_rate ? $return->merchant_shipping_fee:$return['channel_shipping_fees']);
                $channelPaymentGatewayFee = ($return['channel_payment_gateway_fees'] < 0 ? abs($return['channel_payment_gateway_fees']) : 0);
                $unit_price     =   $return->unit_price/1.06;
                $using_price    =   ($channel->channel_detail->sale_amount) ? $return->tp_sale_price : $return->sold_price;
                $total_sales    =   ($channel->channel_detail->money_flow == 'Merchant') ? 0.00 : (($using_price>=0?$using_price:$return->unit_price)/1.06)*$return->original_quantity;
                $totalQuantity  +=  $return->original_quantity;
                //$totalUnitPrice +=  $unit_price;
                $totalSales     +=  $total_sales;
                $channelFee =  $channelCommissionFee + round($channelShippingFee < 0 ? abs($channelShippingFee) : 0) + $channelPaymentGatewayFee;
                $totalChannelCommissionsFee+=  round($channelCommissionFee / 1.06, 2);
                $totalChannelShippingFee +=  round(($channelShippingFee < 0 ? abs($channelShippingFee) : 0) / 1.06, 2);
                $totalChannelPaymentGatewayFee+=  round(($channelPaymentGatewayFee / 1.06), 2);
                $totalChannelFee+=  round($channelFee/ 1.06, 2);
                //$totalHwFee     +=  $return->hw_fee;
                $reportData[] = [
                    'Channel'                   => $channel->name,
                    'Date Ordered'              => Carbon::createFromFormat('Y-m-d H:i:s',$order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order #'                   => $order->id,
                    '3rd Party Order #'         => $order->tp_order_code,
                    'Brand'                     => $return->ref->product->brands->name,
                    'Hubwire SKU'               => $return->ref->sku->hubwire_sku,
                    'Merchant SKU'              => $return->ref->sku->sku_supplier_code,
                    'Product Name'              => $return->ref->product->name,
                    'Size'                      => $return->ref->sku->size,
                    'Color'                     => $return->ref->sku->color,
                    'Quantity'                  => $return->original_quantity,
                    'Unit Price (Exc GST)'      => number_format($unit_price,2,'.',''),
                    'Discounts'                 => round(($using_price > 0 ? ($return->unit_price - $using_price)/$return->unit_price*100 : 0)).'%',
                    'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                    '3rd Party Commission Fees'=> number_format( $channelCommissionFee / 1.06,2,'.',''),
                    '3rd Party Shipping Fees'   => number_format(round($channelShippingFee < 0 ? abs($channelShippingFee) : 0) / 1.06,2,'.',''),
                    '3rd Party Payment Gateway Fees'   => number_format($channelPaymentGatewayFee / 1.06,2,'.',''),
                    'Total 3rd Party Platform Fees'   => number_format($channelFee/ 1.06,2,'.',''),
                    'FM Hubwire Fees (Exc GST)' => '',//number_format($return->hw_fee,2,'.',''),
                ];
                $orders[$return->id]['id']           = $order->id;
                $orders[$return->id]['channel']      = $channel->name;
                $orders[$return->id]['return']       = $total_sales;
                $orders[$return->id]['channelCommissionsFee']   = number_format(($totalChannelCommissionsFee < 0 ? abs($totalChannelCommissionsFee) : 0) / 1.06,2,'.','');
                $orders[$return->id]['channelShippingFee']      = number_format(($totalChannelShippingFee < 0 ? abs($totalChannelShippingFee) : 0) / 1.06,2,'.','');
                $orders[$return->id]['channelPaymentGatewayFee']= number_format(($totalChannelPaymentGatewayFee < 0 ? abs($totalChannelPaymentGatewayFee) : 0) / 1.06,2,'.','');
                $orders[$return->id]['channelFee']   = number_format(($totalChannelFee < 0 ? abs($totalChannelFee) : 0) / 1.06,2,'.','');
                $orders[$return->id]['hubwireFee']   = '';
            }
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
            'TOTAL'                     => 'TOTAL',
            'Quantity'                  =>  $totalQuantity,
            'Unit Price (Exc GST)'      =>  '-',
            'Discounts'                 =>  '-',
            'Total Sales (Exc GST)'     =>  number_format($totalSales,2,'.',''),
            '3rd Party Commission Fees'=>  number_format($totalChannelFee,2,'.',''),
            '3rd Party Shipping Fees'   =>  number_format($totalChannelShippingFee,2,'.',''),
            '3rd Party Payment Gateway Fees'=>  number_format($totalChannelPaymentGatewayFee,2,'.',''),
            'Total 3rd Party Platform Fees'   =>  number_format($totalChannelFee,2,'.',''),
            'FM Hubwire Fees (Exc GST)' =>  '',
        ];
        $response['recon']['data'] = $reportData;
        $response['orders'] = $orders;
        
        return $response;
    }

    private function pendingQuery($merchant_id, $startDateTime, $endDateTime, $gst, $channelTypes, $timezone ="+08:00") {
        
        $response = array();
        $date_range = [$startDateTime, $endDateTime];
        
        $pendings = OrderItem::chargeable()->leftJoin(
            \DB::raw("
                (select
                    `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
                from `order_status_log`
                where `order_status_log`.`to_status` = 'Completed'
                and `order_status_log`.`user_id` = 0 group by order_id
                ) `order_completed`

            "), 'order_completed.order_id', '=', 'order_items.order_id')
            ->select('order_items.*', 'third_party_report.channel_fees', 'third_party_report.channel_shipping_fees', 'third_party_report.channel_payment_gateway_fees', 'third_party_report.id as tp_id', 'orders.channel_id')
            ->leftJoin('third_party_report','third_party_report.order_item_id','=','order_items.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.merchant_id', $merchant_id)
            ->where('third_party_report.item_status', '!=', \DB::raw('order_items.status'))
            ->whereIn('third_party_report.status', ['Verified', 'Unverified'])
            ->whereIn('third_party_report.channel_type_id', $channelTypes)
            ->get();

        $reportData = array();
        $orders = array();
        $check = true;
        $totalQuantity = 0;
        //$totalUnitPrice = 0;
        $totalSales = 0;
        $totalChannelFee = 0;
        $totalHwFee = 0;
        $totalChannelCommissionsFee = 0;
        $totalChannelShippingFee = 0;
        $totalChannelPaymentGatewayFee = 0;
        foreach($pendings as $pending) {
            if ($check) {
                $order          =   $pending->order;
                $channel        =   $order->channel;
                $channelShippingFee = $channel->channel_detail->use_shipping_rate ? $pending->merchant_shipping_fee:$pending['channel_shipping_fees'];
                $unit_price     =   $pending->unit_price/1.06;
                $using_price    =   ($channel->channel_detail->sale_amount) ? $pending->sale_price : $pending->sold_price;
                $total_sales    =   ($channel->channel_detail->money_flow == 'Merchant') ? 0.00 : (($using_price>0?$using_price:$pending->unit_price)/1.06)*$pending->original_quantity;
                $totalQuantity  +=  $pending->original_quantity;
                //$totalUnitPrice +=  $unit_price;
                $totalSales     +=  $total_sales;
                $channelFee = $pending['channel_fees']+$channelShippingFee+$pending['channel_payment_gateway_fees'];
                $totalChannelCommissionsFee+=  $pending['channel_fees'];
                $totalChannelShippingFee+=  $channelShippingFee;
                $totalChannelPaymentGatewayFee+=  $pending['channel_payment_gateway_fees'];
                $totalChannelFee+=  $pending['channel_fees'];
                //$totalHwFee     +=  $pending->hw_fee / 1.06;
                $reportData[] = [
                    'Channel'                   => $channel->name,
                    'Date Ordered'              => Carbon::createFromFormat('Y-m-d H:i:s',$order->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order #'                   => $order->id,
                    '3rd Party Order #'         => $order->tp_order_code,
                    'Brand'                     => $pending->ref->product->brands->name,
                    'Hubwire SKU'               => $pending->ref->sku->hubwire_sku,
                    'Merchant SKU'              => $pending->ref->sku->sku_supplier_code,
                    'Product Name'              => $pending->ref->product->name,
                    'Size'                      => $pending->ref->sku->size,
                    'Color'                     => $pending->ref->sku->color,
                    'Quantity'                  => $pending->original_quantity,
                    'Unit Price (Exc GST)'      => number_format($unit_price,2,'.',''),
                    'Discounts'                 => round(($using_price > 0 ? ($pending->unit_price - $using_price)/$pending->unit_price*100 : 0)).'%',
                    'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                    '3rd Party Commissions Fees'=> number_format($pending['channel_fees'] / 1.06,2,'.',''),
                    '3rd Party Shipping Fees'   => number_format($channelShippingFee / 1.06,2,'.',''),
                    '3rd Party Payment Gateway Fees'=> number_format($pending['channel_payment_gateway_fees'] / 1.06,2,'.',''),
                    'Total 3rd Party Platform Fees'   => number_format($pending['channel_fees'] / 1.06,2,'.',''),
                    'FM Hubwire Fees (Exc GST)' => '',//number_format($pending->hw_fee / 1.06,2,'.',''),
                ];
                $orders[$pending->id]['id']           = $order->id;
                $orders[$pending->id]['channel']      = $channel->name;
                $orders[$pending->id]['pending']      = number_format($total_sales,2,'.','');
                $orders[$pending->id]['channelCommissionsFee']   = number_format($pending['channel_fees']/ 1.06,2,'.','');
                $orders[$pending->id]['channelShippingFee']   = number_format($channelShippingFee/ 1.06,2,'.','');
                $orders[$pending->id]['channelPaymentGatewayFee']   = number_format($pending['channel_payment_gateway_fees']/ 1.06,2,'.','');
                $orders[$pending->id]['channelFee']   = number_format($channelFee/ 1.06,2,'.','');
                $orders[$pending->id]['hubwireFee']   = '';//number_format($pending->hw_fee,2,'.','');
            }
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
            'TOTAL'                     => 'TOTAL',
            'Quantity'                  =>  $totalQuantity,
            'Unit Price (Exc GST)'      =>  '-',
            'Discounts'                 =>  '-',
            'Total Sales (Exc GST)'     =>  number_format($totalSales,2,'.',''),
            '3rd Party Commission Fees'=>  number_format($totalChannelCommissionsFee,2,'.',''),
            '3rd Party Shipping Fees'   =>  number_format($totalChannelShippingFee,2,'.',''),
            '3rd Party Payment Gateway Fees'=>  number_format($totalChannelPaymentGatewayFee,2,'.',''),
            'Total 3rd Party Platform Fees'   =>  number_format($totalChannelFee,2,'.',''),
            'FM Hubwire Fees (Exc GST)' =>  '0.00',//number_format($totalHwFee,2,'.','')
        ];
        $response['recon']['data'] = $reportData;
        $response['orders'] = $orders;
        
        return $response;
    }

    private function paymentQuery($merchant_id, $sales, $returns, $gst, $startdate, $enddate, $timezone ="+08:00") {
        $s_date = $startdate->copy()->format('Y-m-d');
        $e_date = $enddate->copy()->format('Y-m-d');

        $contracts =  Contract::where('merchant_id', $merchant_id)->get()->pluck('brand_id', 'id');//dd($contracts);
        $totalBrandFee = array();
        foreach ($contracts as $contract => $brand) {
            $getBrandFee = Fee::where('contract_id', '=', $contract)->where('end_date', '=', $s_date)->first();
            if (!is_null($getBrandFee)) {
                $brand = Brand::where('id', $brand)->first();
                $brandFee = $getBrandFee->inbound_fee + $getBrandFee->outbound_fee + $getBrandFee->storage_fee + $getBrandFee->shipped_fee + $getBrandFee->return_fee + $getBrandFee->packaging_fee;
                $contractsMg =  Contract::where('id', $contract)->first(['guarantee']);
                if (!is_null($contractsMg['guarantee'])) {
                    $totalBrandFee[$brand->id] = ($getBrandFee->transaction_fee > $contractsMg['guarantee']*1.06 ? $getBrandFee->transaction_fee : $contractsMg['guarantee']*1.06) + $brandFee;
                }else{
                    $totalBrandFee[$brand->id] = $brandFee + $getBrandFee->transaction_fee;
                }
            }
        }
        
        $brands = json_decode(Brand::where('merchant_id', $merchant_id)->where('active', 1)->get()->pluck('0','id'), true);
        foreach (array_keys($totalBrandFee) as $key) {
            if (!isset($brands[$key])) $brands[$key] = $totalBrandFee[$key];
        }
        $totalBrandFee = $brands;
        $channelsDetails = Merchant::with('channels')->where('id', '=', $merchant_id)->first();
        //\Log::info(json_decode($channelsDetails,true));dd(1);
        $response = array();
        $reportData = array();
        $total_sales = 0;
        $total_returns = 0;        
        $total_nett_sales = 0;        
        $total_hw_fee = 0;
        $total_channel_commissions_fees = 0;        
        $total_channel_shipping_fees = 0;        
        $total_channel_payment_gateway_fees = 0;        
        $total_channel_fees = 0;        
       
        foreach($channelsDetails['channels'] as $channel) {
            //$channelGst = ($channel['issuing_company']['gst_reg'])? true: false;
            $total_sale = 0;
            $total_return = 0;
            $nett_sales = 0;
            $hw_fee = 0;
            $channel_commissions_fees = 0;
            $channel_shipping_fees = 0;
            $channel_payment_gateway_fees = 0;
            $channel_fees = 0;
            $balance = 0;
            foreach ($sales as $sale) {
                 if (($channel['name'] == $sale['channel'] && $sale['sale'] >0) || ($channel['name'] == $sale['channel'] && $sale['sale'] <= 0 && $sale['channelFee'] >0)) {
                    $total_sale         +=  $sale['sale'];
                    $total_sales        +=  $sale['sale'];
                    $nett_sales         +=  $sale['sale'];
                    $total_nett_sales   +=  $sale['sale'];
                    $hw_fee             +=  $sale['hubwireFee'] / 1.06;
                    //$total_hw_fee       +=  $sale['hubwireFee'];
                    $channel_commissions_fees           +=  round($sale['channelCommissionsFee'],2);
                    $channel_shipping_fees              +=  round($sale['channelShippingFee'],2);
                    $channel_payment_gateway_fees       +=  round($sale['channelPaymentGatewayFee'],2);
                    $channel_fees                       +=  round($sale['channelFee'],2);
                    $total_channel_commissions_fees     +=  round($sale['channelCommissionsFee'],2);
                    $total_channel_shipping_fees        +=  round($sale['channelShippingFee'],2);
                    $total_channel_payment_gateway_fees +=  round($sale['channelPaymentGatewayFee'],2);
                    $total_channel_fees +=  round($sale['channelFee'],2);
                }
            }


            foreach ($returns as $return) {
                 if (($channel['name'] == $return['channel'] && $return['return'] >0) || ($channel['name'] == $return['channel'] && $return['return'] <= 0 &&$return['channelFee'] >0)) {
                    $total_return       +=  $return['return'];
                    $total_returns      +=  $return['return'];
                    $nett_sales         -=  $return['return'];
                    $total_nett_sales   -=  $return['return'];
                    $hw_fee             -=  $return['hubwireFee'];
                    //$total_hw_fee       -=  $return['hubwireFee'];
                    $channel_commissions_fees           -=  $return['channelCommissionsFee'];
                    $channel_shipping_fees              -=  $return['channelShippingFee'];
                    $channel_payment_gateway_fees       -=  $return['channelPaymentGatewayFee'];
                    $channel_fees                       -=  $return['channelFee'];
                    $total_channel_commissions_fees     -=  $return['channelCommissionsFee'];
                    $total_channel_shipping_fees        -=  $return['channelShippingFee'];
                    $total_channel_payment_gateway_fees -=  $return['channelPaymentGatewayFee'];
                    $total_channel_fees                 -=  $return['channelFee'];
                }
            }
            if ($channel_fees >0 || ($channel_fees <= 0 && $total_sale > 0)) {
                $reportData[$channel->name] = [
                    'Channel'                   => $channel->name,
                    'Total Sales (Exc GST)'     => number_format($total_sale,2,'.',''),
                    'Return'                    => number_format($total_return,2,'.',''),
                    'Total Sales Nett Returns'  => number_format($nett_sales,2,'.',''),
                    'FM Hubwire Fees'           => '',//number_format($hw_fee,2,'.',''),
                    '3rd Party Commission Fees'=> number_format($channel_commissions_fees,2,'.',''),
                    '3rd Party Shipping Fees'   => number_format($channel_shipping_fees,2,'.',''),
                    '3rd Party Payment Gateway Fees'=> number_format($channel_payment_gateway_fees,2,'.',''),
                    'Total 3rd Party Fees'            => number_format($channel_fees,2,'.',''),
                    'Balance to Brands'         => '',
                ];
            }
        }
        $response['brand']['headers'] = [
            'Channel'                   => 'Brands',
            'Total Sales (Exc GST)'     => '',
            'Return'                    => '',
            'Total Sales Nett Returns'  => '',
            'FM Hubwire Fees'           => '',
            '3rd Party Commission Fees'=> '',
            '3rd Party Shipping Fees'   => '',
            '3rd Party Payment Gateway Fees'=> '',
            'Total 3rd Party Fees'            => '',//number_format($channel_fees,2,'.',''),
            'Balance to Brands'         => '',
        ];
        foreach ($totalBrandFee as $id => $value) {
            $value = (is_null($value) ? 0.00 : $value/1.06);
            $brand = Brand::find($id);
            $response['brand'][$brand->prefix] = [
                'Channel'                   => $brand->name,
                'Total Sales (Exc GST)'     => '',
                'Return'                    => '',
                'Total Sales Nett Returns'  => '',
                'FM Hubwire Fees'           => number_format($value, 2),
                '3rd Party Commission Fees'=> '',
                '3rd Party Shipping Fees'   => '',
                '3rd Party Payment Gateway Fees'=> '',
                'Total 3rd Party Fees'            => '',
                'Balance to Brands'         => '',
            ];
            $total_hw_fee +=  $value;
        }

        $response['summary'] = array();
        //if (!empty($reportData)) {
            $response['summary']['0'] = [
                'TOTAL'                     => 'TOTAL (excl GST)',
                'Total Sales (Exc GST)'     => number_format($total_sales,2,'.',''),
                'Return'                    => number_format($total_returns,2,'.',''),
                'Total Sales Nett Returns'  => number_format($total_nett_sales,2,'.',''),
                'FM Hubwire Fees'           => number_format($total_hw_fee,2,'.',''),
                '3rd Party Commission Fees'=> number_format($total_channel_commissions_fees,2,'.',''),
                '3rd Party Shipping Fees'   => number_format($total_channel_shipping_fees,2,'.',''),
                '3rd Party Payment Gateway Fees'=> number_format($total_channel_payment_gateway_fees,2,'.',''),
                'Total 3rd Party Fees'            => number_format($total_channel_fees,2,'.',''),
                'Balance to Brands'         => '-',
            ];
            $response['summary']['1'] = [
                'TOTAL'                     => 'GST 6%',
                'Total Sales (Exc GST)'     => '-',
                'Return'                    => '-',
                'Total Sales Nett Returns'  => number_format($total_nett_sales*($gst?0.06:0.00),2,'.',''),
                'FM Hubwire Fees'           => number_format($total_hw_fee*0.06,2,'.',''),
                '3rd Party Commission Fees'=> '-',
                '3rd Party Shipping Fees'   => '-',
                '3rd Party Payment Gateway Fees'=> '-',
                'Total 3rd Party Fees'            => number_format($total_channel_fees*0.06,2,'.',''),
                'Balance to Brands'         => '-',
            ];
            $balance = $total_nett_sales*($gst?1.06:1.00)-($total_hw_fee*1.06)-($total_channel_fees*1.06);
            $response['summary']['2'] = [
                'TOTAL'                     => 'TOTAL (incl GST)',
                'Total Sales (Exc GST)'     => '-',
                'Return'                    => '-',
                'Total Sales Nett Returns'  => number_format($total_nett_sales*($gst?1.06:1.00),2,'.',''),
                'FM Hubwire Fees'           => number_format($total_hw_fee*1.06,2,'.',''),
                '3rd Party Commission Fees' => '-',
                '3rd Party Shipping Fees'   => '-',
                '3rd Party Payment Gateway Fees'=> '-',
                'Total 3rd Party Fees'            => number_format($total_channel_fees*1.06,2,'.',''),
                'Balance to Brands'         => number_format($balance,2,'.',''),
            ];
        //}
        $response['data'] = $reportData;

        return $response;
    }
    
    protected function createExcel($merchant, $excelData, $startdate, $enddate, $filename, $duration, $fileType, $reportdate){

        return Excel::create($filename, function($excel) use($merchant, $excelData, $startdate, $enddate, $duration, $fileType, $reportdate){
            $time_start = microtime(true);
            foreach ($excelData as $sheetName => $sheetData) {
                
                $sheetName = substr(preg_replace("/[^A-Za-z0-9_.@\-]/", '', $sheetName),0,30);
                
                $dataArray = $sheetData;//array_chunk($sheetData, 3000);
                
                    $excel->sheet($sheetName, function($sheet) use($merchant, $sheetName, $dataArray, $startdate, $enddate, $duration, $fileType, $reportdate) {
                        if ($sheetName == 'RECON') {  
                            $sheet->setColumnFormat(array('D'=>'@','I'=>'@'));
                            reset($dataArray['tables']);
                            $first_key = key($dataArray['tables']);
                                            
                                if(!empty($dataArray['tables']) && !empty($dataArray['tables'][$first_key]['headers']))
                                { 
                                    $borderStyle = array(
                                                      'borders' => array(
                                                          'allborders' => array(
                                                              'style' => PHPExcel_Style_Border::BORDER_THIN
                                                          )
                                                      )
                                                  );
                                    $headers = $dataArray['tables'][$first_key]['headers'];

                                    $endchar =chr(ord('A')+sizeof($headers)-1);

                                    $objDrawing = new PHPExcel_Worksheet_Drawing;
                                    $objDrawing->setPath(public_path('images/fmhw-logo.png')); //your image path
                                    $objDrawing->setCoordinates($endchar.'1');
                                    $objDrawing->setWorksheet($sheet);
                                                        
                                    $brands =  $merchant->brands()->get()->pluck('name');
                                    
                                    $sheet->appendRow( array('FM HUBWIRE SDN BHD (1170852-A)') );
                                    $sheet->appendRow( array('Unit 17-7, Level 7, Block C1, Dataran Prima, Jalan PJU 1/41, 47301 Petaling Jaya, Malaysia') );
                                    $sheet->appendRow( array('Tel: +603 7887 7627') );
                                    $sheet->appendRow( array('Email: accounts.marketplace@hubwire.com') );
                                    $sheet->appendRow( array('') );
                                    
                                    $duration_desc = strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y'));

                                    $sheet->appendRow(array(strtoupper($duration.' RECONCILIATION REPORT: '.$reportdate->format('F Y'))) );
                                    $sheet->mergeCells('A6:'.$endchar.'6');
                                    $sheet->appendRow( array('MERCHANT: '.$merchant->name) );
                                    $sheet->appendRow( array('BRAND(S): '.implode($brands->toArray(),',')) );
                                    $sheet->appendRow( array('ADDRESS: '.$merchant->address) );
                                    $sheet->appendRow( array('REPORT NO: '.strtoupper(substr($merchant->name, 0, 3)).strtoupper($reportdate->format('MY'))) );
                                    $sheet->appendRow( array('REPORT DATE: '.$reportdate->format('d-M-Y') ) );
                                    $sheet->appendRow( array('GST REGISTRATION NO :'.((empty($merchant->gst_reg_no)) ? 'No' : $merchant->gst_reg_no)) );
                                    $sheet->appendRow( array('') );
                                    $sheet->appendRow( array('PAYMENT CONFIRMATION') );
                                    $sheet->mergeCells('A14:B14');
                                    
                                    $sheet->cells('A6', function($cells) {
                                        $cells->setBackground('#404040');
                                        $cells->setFontColor('#ffffff');
                                        $cells->setFont(array(
                                            'size'       => '16',
                                            'bold'       =>  true
                                        ));
                                        $cells->setAlignment('center');
                                    });

                                    $sheet->cells('A14', function($cells) {
                                        $cells->setBackground('#404040');
                                        $cells->setFontColor('#ffffff');
                                        $cells->setFont(array(
                                            'size'       => '16',
                                            'bold'       =>  true
                                        ));
                                        $cells->setAlignment('Right');
                                    });


                                    foreach ($dataArray['tables'] as $k => $data) {
                                        $sheet->appendRow( array('') );
                                        
                                        if(is_string($k)) {
                                            $sheet->appendRow(array($k));
                                        }

                                        if(!empty($data['headers']))
                                            $headers = $data['headers'];
                                        else
                                            $headers = array_keys((array)$data['data'][0]);

                                        $sheet->appendRow($headers);

                                        $headers = array_filter($headers, function($value){
                                                        return ($value !== null && $value !== false && $value !== '');
                                                    });
                                        $row = $sheet->getHighestRow();
                                        $column = $sheet->getHighestColumn($row);

                                        $start = $start_column = ord($column)-(count($headers)-1);
                                        $range = chr($start).$row.':'.$column.$row;
                                        
                                        $sheet->getStyle($range)->applyFromArray($borderStyle);
                                        
                                        $sheet->cells($range , function($row) {
                                            $row->setBackground('#404040');
                                            $row->setFontColor('#ffffff');
                                            $row->setFont(array(
                                                'bold'       =>  true
                                            ));
                                        });
                                       
                                        $start_coordinate = chr($start_column).$sheet->getHighestRow();

                                        // Start dumping the data 
                                        if(empty($data['data'])) {
                                            $sheet->appendRow(array('There is no record found for this particular information.'));
                                        } else {
                                            $sheet->rows($data['data']);
                                        }
                                        
                                        $end_coordinate = $column.$sheet->getHighestRow();
                                        $range = $start_coordinate.':'.$end_coordinate;
                                        
                                        $sheet->getStyle($range)->applyFromArray($borderStyle);
                                        

                                        $sheet->appendRow(array());
                                        if(!empty($data['summary'])) {
                                            $sheet->appendRow($data['summary']);
                                            $summary = array_filter($data['summary'], function($value){
                                                            return ($value !== null && $value !== false && $value !== '');
                                                        });
                                            $row = $sheet->getHighestRow();
                                            $column = $sheet->getHighestColumn($row);

                                            $start = ord($column)-(count($summary)-1);
                                            $range = chr($start).$row.':'.$column.$row;
                                            $sheet->cells($range , function($row) {
                                                $row->setBackground('#1A71B8');
                                                $row->setFontColor('#ffffff');
                                                $row->setFont(array(
                                                    'bold'       =>  true
                                                ));
                                            });

                                            $sheet->getStyle($range)->applyFromArray($borderStyle);
                                            
                                        }
                                        $sheet->appendRow(array(""));
                                        if(!empty($data['pending'])) {
                                            $sheet->appendRow($data['pending']);
                                        }
                                        
                                        
                                    }
                                }
                                $sheet->appendRow(array(""));
                                $sheet->setWidth('A', 20);
                                $sheet->setWidth('B', 20);
                                $sheet->setWidth('D', 15);
                                $sheet->setWidth('E', 35);
                                $sheet->setWidth('F', 30);
                                $sheet->setWidth('G', 15);
                                $sheet->setWidth('H', 35);
                                $sheet->setWidth('L', 20);
                                $sheet->setWidth('M', 20);
                                $sheet->setWidth('N', 20);
                                $sheet->setWidth('O', 25);
                                $sheet->setWidth('P', 25);
                                $sheet->setWidth('Q', 30);
                                $sheet->setWidth('R', 20);
                                $sheet->setWidth('S', 30);
                                $sheet->setHeight(array(
                                    1     =>  12,
                                    2     =>  12,
                                    3     =>  12,
                                    4     =>  12,
                                    5     =>  14,
                                    6     =>  20,
                                    7     =>  12,
                                    8     =>  12,
                                    9     =>  12,
                                    10    =>  12,
                                    11    =>  12,
                                    12    =>  12,
                                    13    =>  12,
                                    14    =>  20,
                                ));
				$sheet->setColumnFormat(array(
                                    'L'=>'#,##0.00',
				    'N'=>'#,##0.00',
				    'O'=>'#,##0.00',
				    'P'=>'#,##0.00',
				    'Q'=>'#,##0.00',
				    'S'=>'#,##0.00',
				));
                        } elseif ($sheetName = 'Payment Report') {
                            reset($dataArray['tables']);
                            $first_key = key($dataArray['tables']);
                                            
                                if(!empty($dataArray['tables']) && !empty($dataArray['tables'][$first_key]['headers'])) { 
                                    $borderStyle = array(
                                                      'borders' => array(
                                                          'allborders' => array(
                                                              'style' => PHPExcel_Style_Border::BORDER_THIN
                                                          )
                                                      )
                                                  );
                                    $headers = $dataArray['tables'][$first_key]['headers'];

                                    $endchar =chr(ord('A')+sizeof($headers)-1);

                                    $objDrawing = new PHPExcel_Worksheet_Drawing;
                                    $objDrawing->setPath(public_path('images/fmhw-logo.png')); //your image path
                                    $objDrawing->setCoordinates($endchar.'1');
                                    $objDrawing->setWorksheet($sheet);
                                                        
                                    $brands =  $merchant->brands()->get()->pluck('name');
                                    
                                    $sheet->appendRow( array('FM HUBWIRE SDN BHD (1170852-A)') );
                                    $sheet->appendRow( array('Unit 17-7, Level 7, Block C1, Dataran Prima, Jalan PJU 1/41, 47301 Petaling Jaya, Malaysia') );
                                    $sheet->appendRow( array('Tel: +603 7887 7627') );
                                    $sheet->appendRow( array('Email: accounts.marketplace@hubwire.com') );

                                    $sheet->appendRow( array('') );
                                    
                                    $duration_desc = strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y'));

                                    $sheet->appendRow(array(strtoupper('PAYMENT REPORT: '.$reportdate->format('F Y'))) );
                                    $sheet->mergeCells('A6:'.$endchar.'6');
                                    $sheet->appendRow( array('MERCHANT: '.$merchant->name) );
                                    $sheet->appendRow( array('BRAND(S): '.implode($brands->toArray(),',')) );
                                    $sheet->appendRow( array('ADDRESS: '.$merchant->address) );
                                   
                                    $sheet->cell(chr(ord($endchar)-1).'7', function($cell) use ($merchant, $reportdate) {
                                    $cell->setValue('REPORT NUMBER: '.strtoupper(substr($merchant->name, 0, 3)).strtoupper($reportdate->format('MY')));
                                    });
                                    $sheet->cell(chr(ord($endchar)-1).'8', function($cell) use ($reportdate) {
                                        $cell->setValue('DATE: '.$reportdate->format('d-M-Y') );
                                    });
                                    $sheet->cell(chr(ord($endchar)-1).'9', function($cell) use ($merchant) {
                                        $cell->setValue('CURRENCY: '.$merchant->currency );
                                    });
                                    $sheet->cell(chr(ord($endchar)-1).'10', function($cell) use ($merchant) {
                                        $cell->setValue('GST REGISTRATION NO: '.((empty($merchant->gst_reg_no)) ? 'No' : $merchant->gst_reg_no) );
                                    });

                                    $sheet->cells('A6', function($cells) {
                                        $cells->setBackground('#404040');
                                        $cells->setFontColor('#ffffff');
                                        $cells->setFont(array(
                                            'size'       => '16',
                                            'bold'       =>  true
                                        ));
                                        $cells->setAlignment('center');
                                    });


                                    foreach ($dataArray['tables'] as $k => $data) {
                                        $sheet->appendRow( array('') );

                                        if(!empty($data['headers']))
                                            $headers = $data['headers'];
                                        else
                                            $headers = array_keys((array)$data['data'][0]);

                                        $sheet->appendRow($headers);

                                        $headers = array_filter($headers, function($value){
                                                        return ($value !== null && $value !== false && $value !== '');
                                                    });
                                        $row = $sheet->getHighestRow();
                                        $column = $sheet->getHighestColumn($row);

                                        $start = $start_column = ord($column)-(count($headers)-1);
                                        $range = chr($start).$row.':'.$column.$row;
                                        
                                        $sheet->getStyle($range)->applyFromArray($borderStyle);
                                        
                                        $sheet->cells($range , function($row) {
                                            $row->setBackground('#404040');
                                            $row->setFontColor('#ffffff');
                                            $row->setFont(array(
                                                'bold'       =>  true
                                            ));
                                        });
                                       
                                        $start_coordinate = chr($start_column).$sheet->getHighestRow();

                                        // Start dumping the data 
                                        if(empty($data['data'])) {
                                            $sheet->appendRow(array('There is no record found for this particular information.'));
                                        } else {
                                            $sheet->rows($data['data']);
                                        }
                                        
                                        $end_coordinate = $column.$sheet->getHighestRow();
                                        $range = $start_coordinate.':'.$end_coordinate;
                                        
                                        $sheet->getStyle($range)->applyFromArray($borderStyle);
                                        
                                        if(!empty($data['brand'])) {
                                            $sheet->appendRow( array('') );
                                            $row = $sheet->getHighestRow();
                                            $column = $sheet->getHighestColumn($row);
                                            $range = 'A'.$row.':J'.$row;
                                            $sheet->getStyle($range)->applyFromArray($borderStyle);
                                            foreach ($data['brand'] as $key => $dataTotal) {
                                                $sheet->appendRow($dataTotal);
                                               
                                                $brand = array_filter($dataTotal, function($value){
                                                    return ($value !== null && $value !== false && $value !== '');
                                                    });
                                                $row = $sheet->getHighestRow();
                                                $column = $sheet->getHighestColumn($row);
                                                $range = 'A'.$row.':J'.$row;
                                                if ($key == 'headers') {
                                                    $sheet->cells('A'.$row.':A'.$row , function($row) {
                                                        $row->setBackground('#404040');
                                                        $row->setFontColor('#ffffff');
                                                        $row->setFont(array(
                                                            'bold'  =>  true,
                                                        ));
                                                        $row->setBorder(array(
                                                            'top'   => array(
                                                                'style' => 'double'
                                                            ),
                                                        ));
                                                    });
                                                }
                                                $sheet->getStyle($range)->applyFromArray($borderStyle);
                                            }
                                            $sheet->appendRow( array('') );
                                            $row = $sheet->getHighestRow();
                                            $column = $sheet->getHighestColumn($row);
                                            $range = 'A'.$row.':J'.$row;
                                            $sheet->getStyle($range)->applyFromArray($borderStyle);
                                        }
                                        
                                        $sheet->appendRow(array());
                                        if(!empty($data['summary'])) {
                                            foreach ($data['summary'] as $dataTotal) {
                                                $sheet->appendRow($dataTotal);
                                                $summary = array_filter($dataTotal, function($value){
                                                            return ($value !== null && $value !== false && $value !== '');
                                                        });
                                                $row = $sheet->getHighestRow();
                                                $column = $sheet->getHighestColumn($row);

                                                $start = ord($column)-(count($summary)-1);
                                                $range = chr($start).$row.':'.$column.$row;
                                                
                                                $sheet->cells($range , function($row) {
                                                    $row->setFont(array(
                                                        'bold'  =>  true,
                                                    ));
                                                    $row->setBorder(array(
                                                        'top'   => array(
                                                            'style' => 'double'
                                                        ),
                                                    ));
                                                });
                                                $sheet->getStyle($range)->applyFromArray($borderStyle);
                                            }
                                        }
                                    }
                                }
                                $sheet->appendRow(array(""));
                                $sheet->setWidth('A', 30);
                                $sheet->setWidth('B', 20);
                                $sheet->setWidth('D', 25);
                                $sheet->setWidth('E', 20);
                                $sheet->setWidth('F', 25);
                                $sheet->setWidth('G', 20);
                                $sheet->setWidth('H', 30);
                                $sheet->setWidth('I', 20);
                                $sheet->setWidth('J', 20);
                                $sheet->cell('A1', function($cell){
                                    $cell->setBorder('double','double','double','double');
                                });
                                $sheet->setHeight(array(
                                    1     =>  12,
                                    2     =>  12,
                                    3     =>  12,
                                    4     =>  12,
                                    5     =>  16,
                                    6     =>  20,
                                ));
				$sheet->setColumnFormat(array(
                                    'B'=>'#,##0.00',
                                    'C'=>'#,##0.00',
                                    'D'=>'#,##0.00',
                                    'E'=>'#,##0.00',
                                    'F'=>'#,##0.00',
                                    'G'=>'#,##0.00',
				    'H'=>'#,##0.00',
                                    'I'=>'#,##0.00',
                                    'J'=>'#,##0.00',
                                ));
                        }
                    });
            }
            
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start)/60;
            $this->info('<b>Chunk Execution Time of create '.$fileType.' ('.$merchant->name.') :</b> '.$execution_time.' Mins');
        })->store($fileType, storage_path('app/reports'));
    }
}
