<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\ThirdParty\Http\Controllers\ShopifyController;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;

class ShopifyPullOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Shopify:PullOrders
                                {--channel_id= : Channel ID}
                                {--date_from= : Create Orders from date in format Y-m-d}
                                {--date_until= : Create Orders until date in format Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Shopify orders.';

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
        $channelId = $this->option('channel_id');
        $shopifyChannelType = ChannelType::whereIn('name', ['Shopify','Shopify POS'])
                                ->where('third_party', '=', 1)
                                ->get();

        $shopifyChannelTypeArray = array();
        foreach ($shopifyChannelType as $key => $value) {
            $shopifyChannelTypeArray[$value->id] = $value->id;
        }

        if (!empty($channelId)) {
            $channel = Channel::with('channel_detail', 'channel_type')
                                ->whereIn('channel_type_id', $shopifyChannelTypeArray)
                                ->where('status', '=', 'Active')
                                ->find($channelId);

            if (!is_null($channel)) {
                $this->pullOrders($channel);
                $this->info('Done.');
            }
            else {
                $this->error('Shopify channel with ID ' . $channelId . ' inactive/not found.');
            }
        }
        else {
            $shopifyChannels = Channel::with('channel_detail', 'channel_type')
                                        ->whereIn('channel_type_id', $shopifyChannelTypeArray)
                                        ->where('status', '=', 'Active')
                                        ->get();

            foreach ($shopifyChannels as $channel) {
                $this->pullOrders($channel);
            }

            $this->info('Done.');
        }
    }

    private function pullOrders($channel) {
        $this->info('Pulling orders from channel ' . $channel->id);

        $controller = new ShopifyController;
        $controller->initialize($channel);

        $dateFrom = date('Y-m-d H:i:s', strtotime($this->option('date_from')));
        $dateUntil = date('Y-m-d H:i:s', strtotime((!is_null($this->option('date_until'))) ? $this->option('date_until') : "now"));

        $filters = array(
            'created_at_min'    => $dateFrom,
            'created_at_max'    => $dateUntil,
            'status'            => 'any',
        );

        $shopify = $controller->api();

        $hasOrders = true;
        $page = 1;

        while ($hasOrders) {
            $filters['page'] = $page;
			// $filters['ids'] = '5151046209';

            $response = $shopify('GET', '/admin/orders.json', $filters);

            if (empty($response) || count($response) == 0) {
                $hasOrders = false;
                continue;
            }

            foreach ($response as $order) {
                $result = $controller->order_create($order);

                $message = 'Order ' . $order['id'] . ' | ';
                $message .= ($result['success']) ? ('Order ' . ((!empty($result['update']) && $result['update']) ? 'updated' : 'created') . ' with id ' . $result['order_id']) : $result['error_desc'];

                if ($result['success']) {
                	$this->info($message);
                }
                else {
                	$this->error($message);
                }
            }

            $page++;
        }
    }
}
