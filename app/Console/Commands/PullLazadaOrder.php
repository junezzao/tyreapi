<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use Log;

class PullLazadaOrder extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Lazada:PullOrders
                            {channel_type : Channel type name ("Lazada" or "LazadaSC")}
                            {--channel_id= : Channel ID}
                            {--date_from= : Create Orders from date in format Y-m-d}
                            {--date_until= : Create Orders until date in format Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Lazada or LazadaSC orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->error_data['subject'] = 'Error '. $this->name;
        $this->error_data['File'] = __FILE__;
    }

	/**
     * Execute the console command.
     *
     * @return mixed
     */
	public function handle()
	{
		$this->info('Running... Lazada:PullOrders');

        $channelTypeName = $this->argument('channel_type');

        if ($channelTypeName != 'Lazada' && $channelTypeName != 'LazadaSC') {
            $this->error('Channel type must be either "Lazada" or "LazadaSC"');
            return;
        }

        $channel_type = ChannelType::where('name', $channelTypeName)->firstOrFail();
        $channel_type_id = $channel_type->id;
        $lazadaController = $channel_type->controller;

        // command variables
        $channel_id = $this->option('channel_id');
        $date_from = strtotime($this->option('date_from'));
        $until = date('Y-m-d H:i:s', strtotime((!is_null($this->option('date_until'))) ? $this->option('date_until') : "now"));

        // Get all Lazada active channels
        $channels = Channel::where('channel_type_id', $channel_type_id)->where('status', 'Active');
        if(!is_null($channel_id))
        {
            $channels = $channels->where('id', $channel_id);
        }
        $channels = $channels->get();

        try
        {
            foreach($channels as $channel)
            {
                $this->info('Processing channel... '. $channel->id);

                $order_proc = new OrderProc($channel->id, new $lazadaController);

                // Set $from
                // If user did not set $from date, get last pulled order date if exist. If not exist, set 7 days earlier as $from date
                if(!$date_from) {
                    $sale = Order::where('channel_id', $channel->id)->orderBy('tp_order_date', 'desc')->first();
                    $from = strtotime((!empty($sale)) ? $sale->tp_order_date : "-7 days");
                }else{
                    $from = $date_from;
                }
                $from = date('Y-m-d H:i:s', $from);

                $this->info("Fetching orders created after : UTC ". $from ." until : UTC ". $until);

                $responses = $order_proc->getAndCreateOrders($from, $until);
                foreach($responses as $response) {
                    if(isset($response['success']) && $response['success']) {
                        $msg = 'Order #'. $response['order_id'] .' was successfully created';
                        $this->info($msg);
                        Log::info($msg);
                    } else {
                        $this->error(MpUtils::errorDescription($response, __METHOD__, __LINE__));
                        Log::error(MpUtils::errorDescription($response, __METHOD__, __LINE__));
                    }
                }
            }
            $this->info('Finished!');
        }
        catch(Exception $e)
        {
            $this->error_data['Command'] = $this->name;
            $this->error_data['ErrorDescription'] = 'Error: '.$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
            $this->error($this->error_data['ErrorDescription']);
            Log::error($this->error_data['ErrorDescription']);
            $order_proc->ErrorAlert($this->error_data);
        }
	}
}
