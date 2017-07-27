<?php namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use DB;
use Storage;
use Log;
use Carbon\Carbon;
use Excel;
use App\Services\Mailer;
use PHPExcel_Worksheet_Drawing;
use PHPExcel_Style_Border;

class GenerateMerchantInvoiceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    //example : php artisan reports:generateReports "reports:generateMerchantInvoiceReport" weekly "2016-10-01 16:00"
    protected $signature = 'reports:generateMerchantInvoiceReport {startdate} {enddate} {emails} {duration?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Merchant Invoices Report';

    private $warehouse_channel_type = 12;
    private $test_merchant = "Test Merchant";
    private $test_channels = "%[Development Client]%";
    private $correction_channels = "%Correction%";

    protected $reportsDirectory = 'reports/merchant';
    
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
        //$emails = $this->argument('emails');
        $emails['to'][] = 'angie@hubwire.com';

        $duration = ($this->argument('duration') != '' ? $this->argument('duration') : '');

        $this->info('Generating report from '. $startdate . ' to '. $enddate);

        $types = ["tax_invoice","credit_note"];
        foreach($types as $type) {
            $resultSet = $this->generateReport($type, $startdate, $enddate, $this->mailer, $duration);
            // \Log::info(print_r($resultSet, true));
            $timestamp = '';
            if (strtolower($duration) == 'weekly') {
                $timestamp = $startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('Ymd');
            } else if (strtolower($duration) == 'monthly') {
                $timestamp = strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('MY')).'_'.Carbon::now()->format('Ymd');
            } else {
                $timestamp = 'undefined';
            }
            $filename = strtoupper(str_slug($type,'_')).'_'.$timestamp;
            $fileType = 'xls';
            $excel_start = microtime(true);
            // timestamp in UTC
            // create excel file
            $excel = $this->createExcel($resultSet, $startdate, $enddate, $filename, $duration, $fileType);
            // dd();
            $file = $filename.'.'.$fileType;
            
            $excel_end = microtime(true);
            $excel_total_exec_time = ($excel_end - $excel_start)/60;

            $this->info('<b>Excel('.$fileType.') Execution Time :</b> '.$excel_total_exec_time.' Mins');
                
            
            // Storage::delete('reports/'.$file);
            // move file to S3
            $uploadedfile = Storage::disk('local')->get('reports/'.$file);
            $s3path = $this->reportsDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$file;
            $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
            
            if($s3upload) {
                $this->info('File Uploaded!');
                // Storage::delete('reports/'.$file);

                $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
                $email_data['url'] = env('AWS_S3_URL').$s3path;
                $email_data['email'] = $emails;
                $email_data['subject'] = $duration . ' '.ucwords(str_replace('_', ' ', $type)).' Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').')';
                $email_data['report_type'] = ucwords(str_replace('_', ' ', $type)).' Report';
                $email_data['duration'] = $duration;
                $email_data['startdate'] = $startdate->format('Y-m-d');
                $email_data['enddate'] = $enddate->format('Y-m-d');
                $this->mailer->scheduledReport($email_data);
            }
        }
        
        $this->info('Completed generating report');
        Log::info('End generating and emailing '.$duration.' Tax Invoice and Credit Note Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').') at '. Carbon::now());
    }

    protected function generateReport($type, $startdate, $enddate, Mailer $mailer, $duration = '') {
        
        $dataArray = array();
        $summaries = array();
        
        ini_set('memory_limit','-1');
        $data = array();
        $data['headers'] = [
                'DOCNO(20)',
                'CODE(10)',
                'DOCDATE',
                'TERMS(10)',
                'DESCRIPTION(200)',
                'AGENT(10)',
                'PROJECT(20)',
                'CURRENCYRATE',
                'DOCAMT',
                'CANCELLED(1)',
                'SEQ',
                'DETAILPROJECT(20)',
                'ACCOUNT(10)',
                'DETAIL DESCRIPTION(200)',
                'TAX(10)',
                'TAXAMT',
                'TAXINCLUSIVE',
                'AMOUNT'
            ];
        
        /**
        ** Invoice
        **/
        if(strcasecmp('tax_invoice', $type)==0) {
            $rows = ($this->invoiceQuery($startdate, $enddate));
        }

        /**
        ** Credit Note
        **/
        if(strcasecmp('credit_note', $type)==0) {

            $rows = ($this->creditNoteQuery($startdate, $enddate));
        }

        foreach($rows as $k => &$row) {
            $channel = Channel::with('channel_type')->find($row['account']);
            $channel_type = $channel->channel_type->name;
            if(strcasecmp('Offline Store',$channel_type) == 0) {
                if(stripos($channel->name, 'gemfive') !== false) $channel_type = 'Gemfive';
                if(stripos($channel->name, 'shopee') !== false) $channel_type = 'Shopee';
            }
            
            $row['account'] = !empty(config('globals.accounts')[$channel->id])?config('globals.accounts')[$channel->id]: (isset(config('globals.accounts')[$channel_type]) ? config('globals.accounts')[$channel_type] : 'undefined');
        }

        $data['data'] = $rows;

        $dataArray[$type]['tables'][] = $data;
        return $dataArray;
    }

    private function invoiceQuery($startDateTime, $endDateTime, $timezone ="+08:00") {

        $response = array();

        $invoice_account = config('globals.invoice_account');

        $query = "SELECT inv.tax_invoice_no AS doc_no,'$invoice_account' AS code,date(inv.created_at) AS doc_date,'30 Days' AS terms,
                CONCAT(DATE_FORMAT(o.shipped_date,'%b %Y'),' Sales - ', ct.name) AS description,m.code AS agent, c.name AS project,
                '1.00' AS currency_rate, o.total AS docamt, IF(oi.status = 'Cancelled' OR oi.status = 'Out of Stock','T','F') AS cancelled,
                '' AS seq,'--' AS detail_project,
                o.channel_id AS account,CONCAT(DATE_FORMAT(o.shipped_date,'%b %Y'),' Sales - ', ct.name) AS detail_description,'SR' AS tax,
                round((oi.sale_price/1.06)*0.06,2) AS taxamt, '0' AS taxinclusive,round(oi.sale_price/1.06,2) AS amount
                FROM order_items oi
                LEFT JOIN orders o ON o.id = oi.order_id  
                LEFT JOIN channels c ON c.id = o.channel_id 
                LEFT JOIN channel_types ct ON ct.id = c.channel_type_id
                LEFT JOIN channel_details cd ON cd.channel_id = c.id
                INNER JOIN order_invoice inv ON inv.order_id = o.id
                LEFT JOIN merchants m ON m.id = oi.merchant_id
                WHERE o.shipped_date BETWEEN '".$startDateTime."' AND '".$endDateTime."'
                AND cd.money_flow = 'FMHW'
                AND (c.issuing_company = '5' OR c.issuing_company = '7')";

        return json_decode(json_encode(DB::select(DB::raw($query))),true);
    }

    private function creditNoteQuery($startDateTime, $endDateTime, $timezone ="+08:00") {

        $response = array();

        $credit_account = config('globals.credit_account');


        $query = "SELECT cn.credit_note_no AS doc_no,'$credit_account' AS code,date(cn.created_at) AS doc_date,'30 Days' AS terms,
        CONCAT(DATE_FORMAT(cn.created_at,'%b %Y'),' Returns - ', ct.name) AS description,m.code AS agent, c.name AS project,
        '1.00' AS currency_rate, o.total AS docamt, IF(oi.status = 'Cancelled' OR oi.status = 'Out of Stock','T','F') AS cancelled,'' AS seq,'--' AS detail_project,
        o.channel_id AS account,CONCAT(DATE_FORMAT(cn.created_at,'%b %Y'),' Returns - ', ct.name) AS detail_description,'SR' AS tax,
        round((oi.sale_price/1.06)*0.06,2) AS taxamt, '0' AS taxinclusive,  round(oi.sale_price/1.06,2) AS amount
        FROM order_items oi
        LEFT JOIN orders o ON o.id = oi.order_id  
        LEFT JOIN channels c on c.id = o.channel_id 
        LEFT JOIN channel_types ct on ct.id = c.channel_type_id
        LEFT JOIN channel_details cd on cd.channel_id = c.id
        LEFT JOIN merchants m on m.id = oi.merchant_id
        INNER JOIN order_credit_note cn on cn.order_id = oi.order_id
        WHERE oi.status = 'returned'
        AND cn.created_at BETWEEN '".$startDateTime."' AND '".$endDateTime."'
        AND cd.money_flow = 'FMHW'
        AND (c.issuing_company = '5' OR c.issuing_company = '7')";
              
        return json_decode(json_encode(DB::select(DB::raw($query))),true);
    }
    
    protected function createExcel($excelData, $startdate, $enddate, $filename, $duration, $fileType){

        return Excel::create($filename, function($excel) use( $excelData, $startdate, $enddate, $duration, $fileType){
            $time_start = microtime(true);
            foreach ($excelData as $sheetName => $sheetData) {
                
                $sheetName = substr(preg_replace("/[^A-Za-z0-9_.@\-]/", '', $sheetName),0,30);
                
                $dataArray = $sheetData;//array_chunk($sheetData, 3000);
                
                    $excel->sheet($sheetName, function($sheet) use( $sheetName, $dataArray, $startdate, $enddate, $duration, $fileType) {
                        reset($dataArray['tables']);
                        $first_key = key($dataArray['tables']);
                                        
                            if(!empty($dataArray['tables']) && !empty($dataArray['tables'][$first_key]['headers']))
                            { 
                                // \Log::info('here');
                                $headers = $dataArray['tables'][$first_key]['headers'];
                                $endchar =chr(ord('A')+sizeof($headers)-1);

                                foreach ($dataArray['tables'] as $k => $data) {
                                    $headers = null;
                                    $start_coordinate = null;

                                    if(!empty($data['headers']))
                                        $headers = $data['headers'];

                                    if(!empty($headers)) {
                                        $sheet->appendRow($headers);
                                    }

                                    // Start dumping the data 
                                    if(empty($data['data'])) {
                                        $sheet->appendRow(array('There is no record found for this particular information.'));
                                    } else {
                                        $sheet->rows($data['data']);
                                    }
                                }
                            }
                    });
            }
            
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start)/60;
            $this->info('<b>Chunk Execution Time of create '.$fileType.':</b> '.$execution_time.' Mins');
        })->store($fileType, storage_path('app/reports'));

    }

}
