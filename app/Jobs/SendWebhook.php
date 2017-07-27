<?php

namespace App\Jobs;

use Exception;
use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Admin\Order;
use App\Models\Admin\Webhook;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\SKU;
use App\Models\Admin\Purchase;
use App\Models\Admin\Channel;

use App\Services\Mailer;
use GuzzleHttp\Exception\RequestException as RequestException;


class SendWebhook extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    private $request,$limit;
    public $webhook;

    
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
        // \Log::info($this->attempts().' sending....'.$this->request['event']);

        $channel_ids = null;
        $request = $this->request;
        /***
        *
        *   Need to find out what is the related channel id(s) to the event
        *
        **/

        try 
        {
            switch ($request['event']) {
                case 'product/created':
                case 'product/updated':
                    $record = Product::with('media', 'brand')->find($request['id']);

                    if (is_null($record)) break;

                    $channel_sku = ChannelSKU::where('product_id', '=', $request['id'])->groupBy('channel_id')->get();
                    $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                    foreach ($channel_sku as $cs) 
                    {
                        $webhook = Webhook::where('channel_id', '=', $cs->channel_id)
                                ->where('topic', '=', $request['event'])
                                ->where('type', '=', 1)
                                ->first();
                        if (!empty($webhook)) 
                        {
                            $this->webhook = $webhook;
                            $client = new \GuzzleHttp\Client();
                            $postman = $client->request('POST', $webhook->address, [
                                'json'=>[$data]
                            ]);
                        }
                    }
                    
                break;
                
                case 'sku/created':
                case 'sku/updated':
                    $record = SKU::findOrFail($request['id']);
                    $channel_sku = ChannelSKU::where('sku_id', '=', $request['id'])->get();
                       
                    foreach ($channel_sku as $cs) 
                    {
                        $data  = ['topic'=>$request['event'],'content'=>$cs->toAPIResponse()];
                        $webhook = Webhook::where('channel_id', '=', $cs->channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])
                                ->first();
                        if (!empty($webhook)) 
                        {
                            $this->webhook = $webhook;
                            $client = new \GuzzleHttp\Client();
                            $postman = $client->request('POST', $webhook->address, [
                                'json'=>[$data]
                            ]);
                        }
                    }
                                 
                break;

                case 'channel_sku/created':
                case 'channel_sku/updated':
                    $request['event'] = ($request['event']=='channel_sku/updated')?'sku/updated':'sku/created';
                    $record = ChannelSKU::with('sku', 'sku_options', 'tags')->findOrFail($request['id']);
                    $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                    $webhook = Webhook::where('channel_id', '=', $record->channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])->first();
                    if (!empty($webhook)) 
                    {
                        $this->webhook = $webhook;
                        $client = new \GuzzleHttp\Client();
                        $postman = $client->request('POST', $webhook->address, [
                            'json'=>[$data]
                        ]);
                    }

                break;
                
                case 'media/created':
                case 'media/updated':
                case 'media/deleted':
                    $record = ProductMedia::withTrashed()->findOrFail($request['id']);
                    $product = Product::with('media', 'brand')->findOrFail($record->product_id);
                    $channel_sku = ChannelSKU::where('product_id', '=', $product->id)->groupBy('channel_id')->get();
                    $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                    foreach ($channel_sku as $cs) 
                    {
                        $webhook = Webhook::where('channel_id', '=', $cs->channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])->first();
                        if (!empty($webhook)) 
                        {
                            $this->webhook = $webhook;
                            $client = new \GuzzleHttp\Client();
                            $postman = $client->request('POST', $webhook->address, [
                                'json'=>[$data]
                            ]);
                        }
                    }
                    
                    
                break;
                
                case 'sales/created':
                case 'sales/updated':
                    $record = Order::with('items', 'member')->findOrFail($request['id']);
                    $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                    $webhook = Webhook::where('channel_id', '=', $record->channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])->first();
                    if (!empty($webhook)) 
                    {
                        $this->webhook = $webhook;
                        $client = new \GuzzleHttp\Client();
                        $postman = $client->request('POST', $webhook->address, [
                            'json'=>[$data]
                        ]);
                    }
                break;
                
                case 'delivery_order/created':
                    $stock_transfer = DeliveryOrder::with('items')->findOrFail($request['id']);
                    $request['event'] = 'sku/updated';
                    $webhook = Webhook::where('channel_id', '=', $stock_transfer->originating_channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])->first();
                    if (!empty($webhook)) 
                    {
                        $this->webhook = $webhook;
                        foreach ($stock_transfer->items as $item) 
                        {
                            $record = ChannelSKU::with('sku', 'sku_options', 'tags')->findOrFail($item->channel_sku_id);
                            $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                            $client = new \GuzzleHttp\Client();
                            $postman = $client->request('POST', $webhook->address, [
                                'json'=>[$data]
                            ]);
                        }
                    }
                break;
                case 'delivery_order/received':
                    $stock_transfer = DeliveryOrder::with('items')->findOrFail($request['id']);
                    $request['event'] = 'sku/updated';
                    $webhook = Webhook::where('channel_id', '=', $stock_transfer->target_channel_id)
                                ->where('type', '=', 1)
                                ->where('topic', '=', $request['event'])->first();
                    if (!empty($webhook)) 
                    {
                        foreach ($stock_transfer->items as $item) 
                        {
                            $this->webhook = $webhook;
                            $channel_sku = ChannelSKU::findOrFail($item->channel_sku_id);
                            $record = ChannelSKU::with('sku', 'sku_options', 'tags')
                                    ->where('sku_id', '=', $channel_sku->sku_id)
                                    ->where('channel_id', '=', $stock_transfer->target_channel_id)
                                    ->firstOrFail();
                            $data  = ['topic'=>$request['event'],'content'=>$record->toAPIResponse()];
                            $client = new \GuzzleHttp\Client();
                            $postman = $client->request('POST', $webhook->address, [
                                'json'=>[$data]
                            ]);
                        }
                    }
                break;
            }
        }
        catch(RequestException $e)
        {
            if(!empty($this->webhook) && $this->attempts() >= $this->limit)
            {
                // Get channel email 
                $channel = Channel::with('channel_detail')->find($this->webhook->channel_id);
                if(!empty($channel) && !empty($channel->channel_detail->support_email) )
                {
                    $mailer = new Mailer;
                    $data['limit'] = $this->limit;
                    $data['title'] = 'WebHook Failed Notification : '.$channel->name;
                    $data['to'] = [$channel->channel_detail->support_email];
                    $data['cc'] = ['jun@hubwire.com'];
                    $data['channel'] = $channel->name;
                    $data['webhook'] = $this->webhook;
                    $data['response'] = json_encode($e->getResponse()->getBody(true));
                    $mailer->WebHookFailedNotification($data);
                }
            }
            throw new Exception($e->getMessage());
        }

    }

    public function send()
    {
        
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */

    // public function failed()
    // {
    //     \Log::info(__FUNCTION__);
    // }

    

}
