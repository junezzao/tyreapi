<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Merchant;
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
use App\Repositories\GenerateReportRepository;

class GenerateMerchantSalesInventoryReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    //example : php artisan reports:generateReports "reports:generateMerchantSalesInventoryReport" weekly "2016-10-01 16:00"
    protected $signature = 'reports:generateMerchantSalesInventoryReport {startdate} {enddate} {emails} {duration?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Merchant Sales/Inventory Report';

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
        $emails = $this->argument('emails');

        $emails['to'][] = 'reports@hubwire.com';

        $this->duration = $duration = ($this->argument('duration') != '' ? $this->argument('duration') : '');

        $this->info('Generating report from '. $startdate . ' to '. $enddate);

        $merchants = Merchant::isActive()->get();

        if(strcasecmp('weekly', $duration) == 0)
        {
            $format = 'WEEKLY REPORT - W'.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('W');
        }
        else 
        {
            $format = 'MONTHLY REPORT - M'.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('m');
        }
            

        $files = array();
        $zip = new ZipArchive;
        $zipFileName = $format.'_MERCHANT_REPORT_'.strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('MY')).'_'.Carbon::now()->format('Ymd').'.zip';
        $create = $zip->open(storage_path('app/reports') . '/' . $zipFileName, ZipArchive::CREATE);
        
        $i=0;
        $report = new GenerateReportRepository(null);
        foreach($merchants as $merchant)
        {
            // if($i > 2) break;
            //Inventory filename
            $resultSet = $report->merchantSalesInventoryReport($merchant->id, $startdate, $enddate, $duration);
            $filename = $format.'_'.strtoupper(str_slug(html_entity_decode($merchant->name),'_')).'_'.strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('MY')).'_'.Carbon::now()->format('Ymd');
            $fileType = 'xls';
            $excel_start = microtime(true);
            // timestamp in UTC
            // create excel file
            $excel = $this->createExcel($merchant, $resultSet, $startdate, $enddate, $filename, $duration, $fileType);
            // dd();
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
            $s3path = $this->reportsDirectory.'/'.$enddate->format('Y').'/'.$enddate->format('m').'/'.$zipFileName;
            $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
            
            if($s3upload){
                $this->info('Zip Uploaded!');
                Storage::delete('reports/'.$zipFileName);

                $email_data['merchant_name'] = (!empty($merchant) ? html_entity_decode($merchant->name) : '');
                $email_data['url'] = env('AWS_S3_URL').$s3path;
                $email_data['email'] = $emails;
                $email_data['subject'] = $duration . ' Merchant Sales & Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').')';
                $email_data['report_type'] = 'Inventory & Sales';
                $email_data['duration'] = $duration;
                $email_data['startdate'] = $startdate->format('Y-m-d');
                $email_data['enddate'] = $enddate->format('Y-m-d');
                $this->mailer->scheduledReport($email_data);
            }
        }
        
        $this->info('Completed generating report');
        Log::info('End generating and emailing '.$duration.' Inventory Report ('.$startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d').') at '. Carbon::now());
    }

    protected function createExcel($merchant, $excelData, $startdate, $enddate, $filename, $duration, $fileType){

        return Excel::create($filename, function($excel) use($merchant, $excelData, $startdate, $enddate, $duration, $fileType){
            $time_start = microtime(true);
            foreach ($excelData as $sheetName => $sheetData) {
                
                // $sheetName = substr(preg_replace("/[^A-Za-z0-9_.@\-]/", '', $sheetName),0,30);
                
                $dataArray = $sheetData;//array_chunk($sheetData, 3000);
                
                    $excel->sheet($sheetName, function($sheet) use($merchant, $sheetName, $dataArray, $startdate, $enddate, $duration, $fileType) 
                    {
                        // $sheet->setColumnFormat(array('D'=>'@','I'=>'@'));
                        
                        if(strcasecmp('FM Hubwire Fees', $sheetName) == 0)
                        {
                            $sheet->setWidth('A', 40);
                            $sheet->setWidth('B', 40);
                            $sheet->setWidth('C', 20);
                            $sheet->setWidth('D', 40);
                            $sheet->setWidth('E', 20);
                            $sheet->setColumnFormat(array('C'=>'0','D'=>'#,##0.00','E'=>'#,##0.00'));
                        }
                        elseif(strcasecmp('Inventory', $sheetName) == 0)
                        {
                            $sheet->setWidth('A', 15);
                            $sheet->setWidth('B', 20);
                            $sheet->setWidth('C', 25);
                            $sheet->setWidth('D', 15);
                            $sheet->setWidth('E', 35);
                            $sheet->setWidth('F', 10);
                            $sheet->setWidth('G', 10);
                            $sheet->setWidth('H', 20);
                            $sheet->setWidth('I', 10);
                            $sheet->setWidth('J', 10);
                            $sheet->setWidth('K', 10);
                            $sheet->setWidth('L', 20);
                            $sheet->setWidth('M', 20);
                            $sheet->setColumnFormat(array('C'=>'@','D'=>'@'));
                        }
                        elseif(strcasecmp('Sales', $sheetName) == 0)
                        {
                            $sheet->setWidth('A', 20);
                            $sheet->setWidth('B', 20);
                            $sheet->setWidth('C', 10);
                            $sheet->setWidth('D', 20);
                            $sheet->setWidth('E', 15);
                            $sheet->setWidth('F', 20);
                            $sheet->setWidth('G', 15);
                            $sheet->setWidth('H', 30);
                            $sheet->setWidth('I', 5);
                            $sheet->setWidth('J', 10);
                            $sheet->setWidth('K', 10);
                            $sheet->setWidth('L', 10);
                            $sheet->setWidth('M', 10);
                            $sheet->setWidth('N', 15);
                            $sheet->setWidth('O', 25);
                            $sheet->setColumnFormat(array(
                                'C'=>'@',
                                'D'=>'@',
                                'G'=>'@',
                                'K'=>'0',
                                'L'=>'#,##0.00',
                                'M'=>'0.00%',
                                'N'=>'#,##0.00',
                                'O'=>'#,##0.00'
                                )
                            );
                        }

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
                                
                                $prefix = '';
                                $format = 'd M-Y';
                                if(strcasecmp($duration, 'weekly')==0)
                                {
                                    $prefix = 'W';
                                    $format = 'd M-Y';
                                }
                                elseif(strcasecmp($duration, 'monthly') == 0)
                                {
                                    $prefix = 'M';
                                    $format = 'M-Y';
                                }

                                if(strcasecmp($sheetName,'inventory')==0)
                                {
                                   $prefix.= 'IR';
                                }
                                elseif(strcasecmp($sheetName,'sales')==0)
                                {
                                   $prefix.= 'SR';
                                }
                                else
                                   $prefix= 'HFR';

                                
                                $report_no = $prefix.'-'.strtoupper($merchant->slug).'-'.strtoupper($enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format($format));
                                
                                $sheet->appendRow( array('') );
                                $sheet->appendRow( array('') );
                                $sheet->appendRow( array('FM HUBWIRE SDN BHD (1170852-A)') );
                                $sheet->appendRow( array('Unit 17-7, Level 7, Block C1, Dataran Prima, Jalan PJU 1/41, 47301 Petaling Jaya, Malaysia') );
                                $sheet->appendRow( array('Tel: +603 7887 7627') );
                                $sheet->appendRow( array('Email: accounts.marketplace@hubwire.com') );
                                $sheet->appendRow( array('GST REGISTRATION NO. 001584476160') );
                                $sheet->appendRow( array('') );
                                
                                $duration_desc = strtoupper($startdate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y').' - '.$enddate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y'));

                                $sheet->appendRow(array(strtoupper($duration.' '.$sheetName.' REPORT')) );
                                $sheet->mergeCells('A9:'.$endchar.'9');
                                $sheet->appendRow( array('') );
                                $sheet->appendRow( array('MERCHANT: '.html_entity_decode($merchant->name)) );
                                $sheet->appendRow( array('BRAND(S): '.implode($brands->toArray(),',')) );
                                $sheet->appendRow( array('ADDRESS: '.$merchant->address) );
                                $sheet->appendRow( array('GST REGISTRATION NO. '.$merchant->gst_reg_no) );
                                
                                
                                $sheet->cell(chr(ord($endchar)-1).'11', function($cell) use ($report_no) {
                                    $cell->setValue('REPORT NUMBER: '.$report_no);
                                });
                                $sheet->cell(chr(ord($endchar)-1).'12', function($cell) use ($duration_desc) {
                                    $cell->setValue('REPORT DURATION: '.$duration_desc);
                                });

                                if(strcasecmp($sheetName,'sales')==0)
                                {
                                    $sheet->cell(chr(ord($endchar)-1).'13', function($cell) use ($merchant) {
                                        $cell->setValue('CURRENCY: '.(!empty($merchant->currency)?$merchant->currency:'MYR'));
                                    });
                                }

                                $sheet->cells('A9', function($cells) {
                                    $cells->setBackground('#404040');
                                    $cells->setFontColor('#ffffff');
                                    $cells->setFont(array(
                                        'size'       => '16',
                                        'bold'       =>  true
                                    ));
                                    $cells->setAlignment('center');
                                });


                                foreach ($dataArray['tables'] as $k => $data) {
                                    $startRight = isset($data['startOnRight']);

                                    $sheet->appendRow( array('') );
                                    
                                    if(is_string($k)) {
                                        $sheet->appendRow(array($k));
                                    }

                                    $headers = null;
                                    $start_coordinate = null;

                                    if(!empty($data['headers']))
                                        $headers = $data['headers'];
                                    // else
                                        // $headers = array_keys((array)$data['data'][0]);


                                    if(!empty($headers))
                                    {
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
                                    }


                                    // Start dumping the data 
                                    if(empty($data['data']))
                                    {
                                        $sheet->appendRow(array('There is no record found for this particular information.'));
                                    }
                                    else    
                                    {
                                        $sheet->rows($data['data']);
                                    }
                                    
                                    if(!empty($start_coordinate))
                                    {
                                        $end_coordinate = $column.$sheet->getHighestRow();
                                        $range = $start_coordinate.':'.$end_coordinate;
                                        
                                        $sheet->getStyle($range)->applyFromArray($borderStyle);
                                    }

                                    $sheet->appendRow(array());
                                    if(!empty($data['summary']))
                                    {
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
                                    
                                    
                                }
                            }
                            $sheet->appendRow(array(""));
                            $sheet->appendRow(array("This is an auto generated report. For any enquiries, please contact your respective account managers."));
                    });
                    
            }
            
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start)/60;
            $this->info('<b>Chunk Execution Time of create '.$fileType.':</b> '.$execution_time.' Mins');
        })->store($fileType, storage_path('app/reports'));

    }

}
