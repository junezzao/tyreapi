<?php

namespace App\Console\Commands;
use DB;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateReports extends Command
{
    /**
     * The name and signature of the console command.
     * php artisan reports:generateReports "reports:generateSalesReport" daily "2016-10-01 16:00"
     * php artisan reports:generateReports "reports:generateSalesReport" weekly "2016-10-01 16:00"
     * php artisan reports:generateReports "reports:generateSalesReport" monthly "2016-10-01 16:00"
     * php artisan reports:generateReports "reports:generateReturnsReport" weekly "2016-10-01 16:00"
     * php artisan reports:generateReports "reports:generateInventoryReport" monthly "2016-10-01 16:00"
     * php artisan reports:generateReports "reports:generateSalesReport" custom "2016-10-02 16:00" "2016-10-01 16:00"
     * @var string
     */
    protected $signature = 'reports:generateReports {reportCommand : To generate type of report.} {duration} {date? : To generate a report for a specific date, enter the date to generate report in yyyy-mm-dd H:i format.} {start?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
    *
    * Set the date for reports to be generated
    *
    */
    protected $report_day = Carbon::MONDAY;

    protected $report_date = 2;

    protected $emails = array('to' => array('reports@hubwire.com'));
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->info('Begin generating reports ...');
            $date = $this->argument('date');
            $custom_start = $this->argument('start');
            $duration = $this->argument('duration');
            if(strtolower($duration) == 'custom' && empty($custom_start)) $this->error('Please set a start date for the report');

            /*
                Available reports
                - reports:generateSalesReport
                - reports:generateReturnsReport
                - reports:generateInventoryReport

             */
            $reportCommand = $this->argument('reportCommand');
            $today = ( $date ? Carbon::createFromFormat('Y-m-d H:i', $date) : Carbon::now());
            $today_kl = $today->copy()->setTimezone('Asia/Kuala_Lumpur');
            $yesterday_kl = $today_kl->copy()->subDay(1);
            if(strtolower($duration) == 'custom'){
                $custom_start = ( $custom_start ? Carbon::createFromFormat('Y-m-d H:i', $custom_start) : '');
                $custom_start_kl = $custom_start->copy()->setTimezone('Asia/Kuala_Lumpur');
            }

            $this->info("Today GMT+8 is ".$today_kl);

            $this->info("Begin generating $duration $reportCommand ...");
            $duration  = ucwords($duration);
            switch ($duration) {
                case 'Daily':
                    # code...
                    $start_kl = $yesterday_kl->copy()->startOfDay();
                    $end_kl = $yesterday_kl->copy()->endOfDay();

                    break;
                case 'Weekly':
                    /* always get last week's report
                    if today is the first DAY of the month generate till the last day previous month
                    if today is the first REPORT DAY (MONDAY) of the month generate from the first day of the month till the subsequent Monday
                    */

                    if ($today_kl->dayOfWeek == $this->report_day ||$today_kl->isSameDay($today_kl->copy()->startOfMonth()))
                    {
                        /*
                            If is report day (Monday)
                            -OR-
                            If today is first day of the month generate. Generate last weekly report for last month
                         */
                        $end_kl = $yesterday_kl->copy()->endOfDay();
                    }else{
                        /*
                            If not report day get last report day
                            /*
                                "startOfWeek" is used instead of "previous report day" because sometime report day, the day of the current report day is needed.
                                E.g if today is Tuesday and report day is Monday , the last report day is "This Monday" and not the "Previous monday"

                                "addDay(($this->report_day-1))"" is to adjust the date according to report day if report day is not a monday.
                         */

                        $last_report_day = $yesterday_kl->copy()->startOfWeek()->addDay(($this->report_day-1));

                        if($last_report_day < $today_kl->copy()->startOfMonth()){
                            // if last report day is on month end , generate from end of last month
                            $end_kl = $yesterday_kl->copy()->startOfMonth()->subSecond(1); // end of last month
                        }else{

                            $end_kl = $yesterday_kl->copy()->startOfWeek()->addDay(($this->report_day-1))->subSecond(1);
                        }
                    }

                    /*
                        If month begins in the middle of the week , generate report from first of the month
                        else generate from closest report day of end date
                    */
                    $start_kl = $end_kl->copy()->startOfWeek()->addDay(($this->report_day-1)); //current report day

                    if ($start_kl->month != $end_kl->month) $start_kl = $end_kl->copy()->startOfMonth();
                    break;
                case 'Monthly':
                        /*
                            Get last month's report
                         */
                        $start_kl = $today_kl->copy()->subMonth()->startOfMonth();
                        $end_kl = $today_kl->copy()->subMonth()->endOfMonth();
                    break;
                case 'Custom':
                        /*
                            Get custom start and end date
                        */
                        $start_kl = $custom_start_kl->copy()->startOfDay();
                        $end_kl = $today_kl->copy()->endOfDay();
                    break;
                default:
                    # code...
                    $this->error('duration only supports Daily , Weekly or Monthly');
                    return false;
                    break;
            }

            $start = $start_kl->copy()->setTimezone('UTC');
            $end = $end_kl->copy()->setTimezone('UTC');
            $this->info('UTC start time: ' .$start);
            $this->info('UTC end time : '.$end);

            $this->call($reportCommand, [
                    'startdate' => $start->copy(), 'enddate' => $end->copy(), 'duration' => $duration, 'emails' => $this->emails
            ]);
        } catch (Exception $e) {

        }
    }
}
