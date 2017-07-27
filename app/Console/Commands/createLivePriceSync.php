<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Admin\ChannelSKU;
use App\Repositories\Eloquent\SyncRepository;



class createLivePriceSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /*
     * php artisan sync:CreateLivePrice "2017-03-07 15:55"
     * This command creates updatePrice syncs for SKUs that having sales period starts on 2017-03-08 or ends on 2017-03-07
     */
    protected $signature = 'sync:CreateLivePrice {datetime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check & Create Sync Channel SKU Live Price';

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
        $datetime = !empty($this->argument('datetime'))?$this->argument('datetime'):date('Y-m-d H:i');
        $datetime = Carbon::createFromFormat('Y-m-d H:i', $datetime)->copy()->setTimezone('Asia/Kuala_Lumpur');
        $start_date = $datetime->copy()->format('Y-m-d H:i');
        $end_date = $datetime->copy()->format('Y-m-d');
        $this->info('Running sync:CreateLivePrice...');
        \Log::info('Running sync:CreateLivePrice...');
        $this->info('Start Date: '.$start_date);
        \Log::info('Start Date: '.$start_date);
        $this->info('End Data: '.$end_date);
        \Log::info('End Data: '.$end_date);
        
        // List about to starts
        $start_list  = ChannelSKU::whereRaw(" DATE_SUB(promo_start_date, interval 5 minute) <= '$start_date' AND promo_start_date >= '$start_date' ")
                    ->chunk(1000, function($channel_skus){
                            foreach ($channel_skus as $channel_sku) {
                                $syncRepo = new SyncRepository;
                                $input['channel_sku_id'] = $channel_sku->channel_sku_id;
                                $sync = $syncRepo->updatePrice($input);
                            }
                        });

        // List about to ends
        $end_list  = ChannelSKU::whereRaw(" DATE_ADD(promo_end_date, interval 55 minute) >= '$end_date' AND promo_end_date <= '$end_date' ")
                    ->chunk(1000, function($channel_skus){
                            foreach ($channel_skus as $channel_sku) {
                                $syncRepo = new SyncRepository;
                                $input['channel_sku_id'] = $channel_sku->channel_sku_id;
                                $sync = $syncRepo->updatePrice($input);
                            }
                        }); 

        $this->info('Finished running sync:CreateLivePrice!!');
        \Log::info('Finished running sync:CreateLivePrice!!');
    }
}
