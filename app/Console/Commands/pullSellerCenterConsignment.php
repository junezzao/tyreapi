<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\LazadaController as SellerCenterController;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;

class pullSellerCenterConsignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sellerCenter:pullConsignment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull consignment numbers for all orders belonging to seller center channels';

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
        $time_start = microtime(true);
        try 
        {
            $types = ChannelType::whereIn('name',['Lazada','Zalora'])->get()->lists('id');
            $channels = Channel::whereIn('channel_type_id', $types->toArray() )
                        ->where('status','=','active')
                        ->with('channel_detail')->get();//->lists('id');
            $not_found = array();
            $sellerCenter = new SellerCenterController;
            foreach($channels as $channel)
            {
                $this->info('Processing channel '. $channel->name.'\'s orders....');
                $i=0;
                $orders = array();
                            
                Order::where('channel_id','=', $channel->id )
                ->where('status','>=',21)
                ->where('consignment_no','=','')
                ->where('cancelled_status','<>',1)
                ->chunk(20,function($data) use ($sellerCenter,$channel,&$i, &$orders) {
                    ++$i;
                    // $this->info($channel->name.'\'s...'.($i));

                    $ids = $data->lists('tp_order_id')->toArray() ;
                    foreach($ids as $id)
                    {
                        $orders[$id]['OrderId'] = $id;
                    }
                    $sellerCenter->getMultipleOrderItems( $orders, $channel);
                    // sleep(5);
                });
                
                $j=0; $k=0; $l=0; $m=0;
                foreach($orders as $order)
                {
                    $k++;
                    if(empty($order['OrderItems']))
                    {
                        // \Log::info(print_r($order, true));
                        $l++;
                    }
                    else
                    {
                        if(empty($order['OrderItems'][0]['TrackingCode']))
                        {
                            if(!empty($order['OrderItems'][0]))
                            {
                                $m++;
                            }
                        }
                        else
                        {
                            $j++;
                            $consignment_no =  $order['OrderItems'][0]['TrackingCode'];
                            Order::where('channel_id','=',$channel->id)->where('tp_order_id','=',$order['OrderId'])->update(['consignment_no'=>$consignment_no]);
                            // $this->info('Tracking No for Order #'.$order['OrderId'].': '.$consignment_no);
                        }
                    }
                }
                $this->info($k.' order(s) found in database!');
                $this->info($l.' order(s) having problem getting items info from marketplace!');
                $this->info($m.' order(s) did not have tracking code!');
                $this->info($j.' order(s) updated with consignment no!');
                $this->info('=========================================');
                
            }
        }
        catch(\Exception $e)
        {   
            $this->error("there's error");
            $this->error($e->getMessage());
        }
        
        $time_end = microtime(true);
        $time = ceil(($time_end - $time_start)/60);

        $this->info("Finish in ~$time minute(s)");
        
    }
}
