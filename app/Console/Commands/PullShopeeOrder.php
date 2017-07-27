<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use Monolog;

class PullShopeeOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Shopee:PullOrders
                            {--channel_id= : Channel ID}
                            {--tp_order_id= : Third Party Order ID}
                            {--date_from= : Create Orders from date in format Y-m-d}
                            {--date_until= : Create Orders until date in format Y-m-d}';
    /* Note: Shopee tp_order_id is the same as tp_order_code, which contains non-numeric characters. */

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Shopee Orders';

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

        $this->customLog = new Monolog\Logger('Shopee Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/shopee.log', Monolog\Logger::INFO));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Running... Shopee:PullOrders');

        $channelTypeName = 'Shopee';

        $channel_type = ChannelType::where('name', $channelTypeName)->firstOrFail();
        $channel_type_id = $channel_type->id;
        $mp_controller = $channel_type->controller;

        // command variables
        $channel_id = $this->option('channel_id');
        $date_from = strtotime($this->option('date_from'));
        $until = date('Y-m-d H:i:s', strtotime((!is_null($this->option('date_until'))) ? $this->option('date_until') : "now"));

        // Get all active channels
        $channels = Channel::where('channel_type_id', $channel_type_id)->where('status', 'Active');
        if(!is_null($channel_id))
        {
            $channels = $channels->where('id', $channel_id);
        }
        $channels = $channels->get();

        try
        {
            if(!is_null($this->option('tp_order_id')) && $this->option('tp_order_id')!='' )
            {
                try
                {
                    $channel = $channels[0];
                    $order_proc = new OrderProc($channel->id, new $mp_controller);
                    $order = $order_proc->getSingleOrder($this->option('tp_order_id'));
                    $response = $order_proc->createOrder($channel->id, $order);

                    if(empty($response['success']) || !$response['success'] )
                    {
                        $this->error(!empty($response['error_desc'])?$response['error_desc']:'Unknown error!');
                    }
                }
                catch(Exception $e)
                {
                    $this->error($e->getMessage());
                }

            }
            else
            {
                foreach($channels as $channel)
                {
                    $this->info('Processing channel... '. $channel->id);

                    $order_proc = new OrderProc($channel->id, new $mp_controller);

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

                    do
                    {
                        $temp_until = date('Y-m-d H:i:s', strtotime($from ." +15days"));
                        $this->info("Fetching orders created after : UTC ". $from ." until : UTC ". $temp_until);

                        $responses = $order_proc->getAndCreateOrders($from, $temp_until);

                        foreach($responses as $response) {
                            if(isset($response['success']) && $response['success']) {
                                $msg = 'Order #'. $response['order_id'] .' was successfully created';
                                $this->info($msg);
                                $this->customLog->addInfo($msg);
                            } else {
                                $this->error(MpUtils::errorDescription($response, __METHOD__, __LINE__));
                                $this->customLog->addError(MpUtils::errorDescription($response, __METHOD__, __LINE__));
                            }
                        }
                        // increment $from
                        $from = $temp_until;
                    } while ($temp_until < $until);
                }
            }

            $this->info('Finished!');
        }
        catch(Exception $e)
        {
            $this->error_data['Command'] = $this->name;
            $this->error_data['ErrorDescription'] = 'Error: '.$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
            $this->error($this->error_data['ErrorDescription']);
            $this->customLog->addError($this->error_data['ErrorDescription']);
            $order_proc->ErrorAlert($this->error_data);
        }
    }
}
