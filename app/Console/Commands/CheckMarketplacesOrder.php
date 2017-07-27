<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Helpers\DateTimeUtils as DateTimeUtils;
use App\Modules\ThirdParty\Http\Controllers\ShopifyController;
use App\Models\User;
use App\Models\Admin\Order;
use App\Models\Admin\FailedOrder;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Services\Mailer;
use Carbon\Carbon;

class CheckMarketplacesOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // php artisan command:checkMarketplacesOrder --date_from="2017-1-1" --date_until="2017-1-1" --channel_id= 13 --marketplace="shopify"
    protected $signature = 'command:checkMarketplacesOrder
                            {--date_from= : Create Orders from date in format Y-m-d}
                            {--date_until= : Create Orders until date in format Y-m-d}
                            {--marketplace= : LazadaSC, Zalora, 11Street, Shopify}
                            {--channel_id= : Channel ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Marketplaces Misssing Order';

    protected $emails = array('to' => array('jun@hubwire.com'),
                              'cc' =>array(
                                    'rachel@hubwire.com',
                                    'hehui@hubwire.com',
                                )
                            );

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
        $applicableMarketplaces = ['LazadaSC', 'Zalora', '11Street', 'Shopify', 'Shopify POS'];

        /*
         * Options
         */
        $selectedMarketplace = $this->option('marketplace');
        $channel_id = $this->option('channel_id');
        $date_from = $this->option('date_from');
        $date_until = $this->option('date_until');

        /*
         * Options Verification
         */
        if (empty($date_from)) {
            if (!empty($date_until)) {
                $this->error('Please provide date_until');
                return;
            }
        }else if (!empty($date_from)) {
            if (empty($date_until)) {
                $this->error('Please provide date_from');
                return;
            }
        }

        if (!is_null($selectedMarketplace) && !in_array($selectedMarketplace, $applicableMarketplaces)) {
            $this->error("Please provide an applicable marketplace i.e. " . implode($applicableMarketplaces, ', ') . ".");
            return;
        }

        if (!is_null($channel_id)) {
            $selectedChannel = Channel::with('channel_type')->where('status', '=', 'Active')->find($channel_id);

            if (is_null($selectedChannel)) {
                $this->error('The provided channel ID is invalid. The channel either does not exist or is inactive.');
                return;
            }
            else {
                if (!in_array($selectedChannel->channel_type->name, $applicableMarketplaces)) {
                    $this->error("The provided channel ID is not an applicable marketplace i.e. " . implode($applicableMarketplaces, ', ') . ".");
                    return;
                }

                if (!is_null($selectedMarketplace) && $selectedChannel->channel_type->name != $selectedMarketplace) {
                    $this->error("The provided channel ID and marketplace does not match.");
                    return;
                }
            }
        }

        /*
         * Checking....
         */
        $dateFrom = (!is_null($date_from))? Carbon::createFromFormat('Y-m-d', $date_from)->startOfDay()->toDateTimeString() : Carbon::yesterday()->setTimezone('Asia/Kuala_Lumpur')->startOfDay()->toDateTimeString();
        $dateUntil = (!is_null($date_until))? Carbon::createFromFormat('Y-m-d', $date_until)->endOfDay()->toDateTimeString() : Carbon::yesterday()->setTimezone('Asia/Kuala_Lumpur')->endOfDay()->toDateTimeString();

        $marketplace = (!is_null($selectedMarketplace)) ? [$selectedMarketplace] : ((!is_null($channel_id)) ? [$selectedChannel->channel_type->name] : $applicableMarketplaces);

        $this->info("Checking " . (!is_null($channel_id) ? (" channel $channel_id (" . $selectedChannel->channel_type->name) : (" marketplaces (" . implode($marketplace, ', '))) . ") missing order from UTC $dateFrom to UTC $dateUntil...");

        $dateRange = [$dateFrom, $dateUntil];
        $responses = array();
        $tpOrder = array();
        $orderCount = array();

        foreach ($marketplace as $name) {
            $this->info("Processing marketplace: $name...");

            $channel_type = ChannelType::where('name', $name)->first();
            $channel_type_id = $channel_type->id;
            $controller = $channel_type->controller;
            $channels = Channel::where('channel_type_id', $channel_type_id)->where('status', 'Active');

            if(!is_null($channel_id)){
                $channels = $channels->where('id', '=', $channel_id);
            }

            $channels = $channels->get();

            foreach ($channels as $channel) {
                $this->info("Processing channel $channel->id... ");
                $orderCount[$channel->name] = 0;

                if (in_array($name, ['Shopify', 'Shopify POS'])) {
                    $controller = new ShopifyController;
                    $controller->initialize($channel);
                    $shopify = $controller->api();

                    $filters = array(
                        'created_at_min'    => $dateFrom,
                        'created_at_max'    => $dateUntil,
                        'status'            => 'any',
                    );

                    $responses = $shopify('GET', '/admin/orders.json', $filters);
                    if (!empty($responses)) {
                        foreach ($responses as $key => $value) {
                            $orderCount[$channel->name]++;
                            $tpOrder[$value['id']]['id'] = $value['id'];
                            $tpOrder[$value['id']]['channel_name'] = $channel->name;
                            $tpOrder[$value['id']]['date'] = self::convertToHWDateTime($value['created_at']);
                        }
                    }

                }else{
                    $order_proc = new OrderProc($channel->id, new $controller);
                    $responses = $order_proc->getOrders($dateFrom, $dateUntil);

                    if (!empty($responses)) {
                        foreach ($responses as $key => $value) {
                            if ($value['success']==true) {
                                $orderCount[$channel->name]++;
                                $id = $value['order']['tp_order_id'];
                                $tpOrder[$id]['id'] = $id;
                                $tpOrder[$id]['channel_name'] = $channel->name;
                                $tpOrder[$id]['date'] = $value['order']['tp_order_date'];
                            }
                        }
                    }
                }
            }
        }

        $orders = Order::whereBetween('tp_order_date', $dateRange)->get();
        $this->info('Checking existing orders...');

        foreach ($orders as $order) {
            unset($tpOrder[$order->tp_order_id]);
        }
        $failedOrders = FailedOrder::whereBetween('tp_order_date', $dateRange)->get();
        $this->info('Checking failed order...');
        foreach ($failedOrders as $order) {
            if (isset($tpOrder[$order->tp_order_id])) {
                $email = User::where('id', '=', $order->user_id)->get(['email']);
                $tpOrder[$order->tp_order_id]['errorMessage'] = $order->error;
                $tpOrder[$order->tp_order_id]['status'] = $order->status;
                $tpOrder[$order->tp_order_id]['user'] = empty($email)? '' : $email[0]['email'];
            }
        }

        //setup email data
        $this->info('Sending emails...');
        $data = $this->emails;
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateFrom)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y');
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateUntil)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y');
        $data['title'] = 'Orders from '.$startDate.' 8AM to'.$endDate.' 8AM';
        $data['orderCount'] = $orderCount;
        $data['totalMissing'] = count($tpOrder);
        $data['missingOrder'] = $tpOrder;

        //create email
        $this->mailer->marketplaceMissingOrder($data);

        $this->info('Done.');
    }

    public static function convertToHWDateTime($shopifyDate) {
        return DateTimeUtils::convertTime($shopifyDate, 'Y-m-d H:i:s', 'UTC', 'UTC');
    }
}
