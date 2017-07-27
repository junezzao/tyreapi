<?php

namespace App\Jobs;

use Exception;
use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Repositories\GenerateReportRepository;

use App\Models\Admin\Merchant;
use Carbon\Carbon;
use Artisan;


class GenerateReport extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    private $request,$limit;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
        $this->limit = 5; // set it same to the --tries option on queue:listen command
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->request;
        ini_set('max_execution_time', 3000);
        $startDate = Carbon::createFromFormat('M-Y', $data['month'])->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('M-Y', $data['month'])->endOfMonth()->toDateString();
        $startDateTime = Carbon::createFromFormat('M-Y', $data['month'])->startOfMonth()->startOfDay()->setTimezone('Asia/Kuala_Lumpur');
        $endDateTime = Carbon::createFromFormat('M-Y', $data['month'])->endOfMonth()->startOfDay()->setTimezone('Asia/Kuala_Lumpur');
        $channelTypes = empty($data['channel']) ? ['all'] : $data['channel'];
        \Log::info('Start generate report '.Carbon::now());
            Artisan::call('calculate:fees', [
                'feeType' => "HubwireFee", 
                '--start_date' => $startDate,
                '--end_date' => $endDate,
                'merchant' => $data['merchant'],
            ]);
            Artisan::call('calculate:fees', [
                'feeType' => "ChannelFee", 
                '--start_date' => $startDate,
                '--end_date' => $endDate,
                'merchant' => $data['merchant'],
            ]);
            Artisan::call('command:moveShopifyTpReportOrder', [
                '--start_date' => $startDate,
                '--end_date' => $endDate
            ]);
            Artisan::call('command:compareChannelFeeChannelMg');
            //count hubwire fee
            if (empty($data['merchant'])) {
                $merchants = Merchant::isActive()->get();
            }else{
                $merchants = Merchant::isActive()->whereIn('id', $data['merchant'])->get();
            }
            foreach ($merchants as $merchant) {
                \Log::info('Count '.$merchant->name.' Hubwire Fee.');
                $report = new GenerateReportRepository(null);
                $report->merchantInventoryQuery($merchant->id, $startDateTime, $endDateTime);
            }
            foreach ($channelTypes as $channelType) {
                Artisan::call('reports:generateMerchantPaymentReport', [
                    'date' => Carbon::createFromFormat('Y-m-d H:i:s', $endDateTime)->addDays(1)->format('Y-m-d H:i'),
                    'emails' => "reports@hubwire.com",
                    '--merchant' => $data['merchant'],
                    '--channelType' => $channelType,
                ]);
            }
        \Log::info('Generate report '.Carbon::now());
    }   
}