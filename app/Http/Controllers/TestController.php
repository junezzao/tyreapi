<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use App\Http\Requests;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\SendWebhook;
use Carbon\Carbon;
use DB;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\SKU;
use App\Models\Admin\Merchant;
use App\Modules\Contracts\Repositories\Eloquent\ContractRepository as ContractRepo;

class TestController extends Controller
{

    private $accessTokenUrl = '/1.0/oauth/access_token';

    private $postActions = array('Create order', 'Create webhook');

    private $deleteActions = array('Delete webhook');

    private $putActions = array('Update webhook');

	public function index()
	{
        $data = array();

        $actions = array(
            'Create order'                      => 'Create order',
            'List orders'                       => 'List orders',
            'Get order'                         => 'Get order',
            'List order items within an order'  => 'List order items within an order',
            'Get single order item'             => 'Get single order item',
            'List products'                     => 'List products',
            'Get product'                       => 'Get product',
            'Get SKU'                           => 'Get SKU',
            'Create webhook'                    => 'Create webhook',
            'Delete webhook'                    => 'Delete webhook',
            'List webhooks'                     => 'List webhooks',
            'Update webhook'                    => 'Update webhook',
        );

        $actionsUrl = array(
            'Create order'                      => route('1.0.sales.store'),
            'List orders'                       => route('1.0.sales.index') . '?limit=5',
            'Get order'                         => route('1.0.sales.show', ['sale'=>":sale_id"]),
            'List order items within an order'  => url('/1.0/sales/:sale_id/items') . '?limit=5',
            'Get single order item'             => url('/1.0/sales/:sale_id/items/:item_id'),
            'List products'                     => route('1.0.products.index') . '?limit=5',
            'Get product'                       => route('1.0.products.show', ['product'=>':product_id']),
            'Get SKU'                           => route('1.0.sku.show', ['sku'=>':sku_id']),
            'Create webhook'                    => route('1.0.webhooks.store'),
            'Delete webhook'                    => route('1.0.webhooks.destroy', ['webhooks'=>':webhook_id']),
            'List webhooks'                     => route('1.0.webhooks.index'),
            'Update webhook'                    => route('1.0.webhooks.update', ['webhooks'=>':webhook_id']),
        );

        $sampleRequests = array(
            'Create order'      => '{"order_number": "#6414","order_date": "2016-05-30 11:00:00","total_price": 138.46,"total_discount": 0,"shipping_fee": 138.46,"currency": "MYR","payment_type": "paypal","status": "paid","shipping_info": {"recipient": "Mahadhir Mohd Asnawi","phone": "01133772723","tracking_no": "123qwe","address_1": "No 12 Jalan Kiai Khusairi 3","address_2": "Taman Aneka Baru","city": "Klang","postcode": "41250","state": "Selangor","country": "Malaysia"},"items": [{"sku_id": 1766,"quantity": 1,"price": 74.5,"discount": 1,"tax": 3.2,"tax_inclusive": 1}],"customer": {"name": "Mahadhir Mohd Asnawi","email": "xspinners@gmail.com","phone": "44"}}',
            'Create webhook'    => '{"topic":"sales/created","address":"http://testing.com"}',
            'Update webhook'    => '{"topic":"sales/created","address":"http://testing.com"}',
        );

        // $events = array(
        //     'sales/created'     => 'sales/created',
        //     'sales/updated'     => 'sales/updated',
        //     'product/created'   => 'product/created',
        //     'product/updated'   => 'product/updated',
        //     'sku/created'       => 'sku/created',
        //     'sku/updated'       => 'sku/updated',
        //     'media/created'     => 'media/created',
        //     'media/deleted'     => 'media/deleted'
        // );

        $data['sampleRequests']     = $sampleRequests;
        $data['apiRequireBody']     = array_merge($this->postActions, $this->putActions);
        $data['apiUrl']             = url('/1.0');
        $data['actions']            = $actions;
        $data['actionsUrl']         = $actionsUrl;
        // $data['events']             = $events;

        return view('api-explorer.index', $data);
    }

    public function performAction(Request $request)
    {
        $requestUrl = $request->input('requestUrl');
        $requestUrl = parse_url($requestUrl);
        $header = array();
        $body = '';
        $header['Authorization'] = $request->input('accessToken');

        // check if action is in postActions, if yes, add in additional header and get type
        if(in_array($request->input('action'), $this->postActions)){
            $body = $request->input('requestBody');
            $header['Content-Type'] = 'application/json';
            $type = 'POST';
        }elseif(in_array($request->input('action'), $this->deleteActions)){
            $type = 'DELETE';
        }elseif(in_array($request->input('action'), $this->putActions)){
            $type = 'PUT';
            $body = $request->input('requestBody');
            $header['Content-Type'] = 'application/json';
        }else{
            $type = 'GET';
        }

        // process the URL
        $url = $requestUrl['path'];
        if(isset($requestUrl['query'])){
            $url = $url . '?' . $requestUrl['query'];
        }

        // send request
        $response = $this->guzzleRequest($type, $url, $header, $body);

        return $response;
    }



    public function performWebhookAction(Request $request)
    {
        // get channel id from credential
        $clientId = $request->input('clientId');
        $clientSecret = $request->input('clientSecret');
        $event = $request->input('webhookEvent');
        $refId = $request->input('refId');
        $channelId = DB::table('oauth_clients')->select('authenticatable_id')->where('id', '=', $clientId)->where('secret', '=', $clientSecret)->first();
        $response = array();

        // validate event and ref id whether it is in the channel id
        $valid = $this->validateWebhookEvent($event, $refId, $channelId->authenticatable_id);

        if(!$valid){
            // return an invalid response
            $response['error'] = true;
            $response['message'] = 'Unable to find matching ref ID.';
        }else{
            $webhook = DB::table('webhooks')->where('channel_id','=',$channelId->authenticatable_id)
                        ->where('topic','=',$event)
                        ->where('type','=',1)->first();
            
            // dispatch webhook
            // dispatch( new SendWebhook(['id'=>$refId, 'event'=>$event]) );

            // mock a response
            $response['data'] = $this->mockWebhookResponse($event, $refId, $channelId->authenticatable_id);
            $post = $this->guzzleRequest('POST', $webhook->address, null, json_encode($response['data']) );
            $response['response'] = !empty(json_decode($post))?json_decode($post):['OK (200)'];
        }

        return response()->json($response);
    }

    public function mockWebhookResponse($event, $refId, $channelId){
        switch ($event) {
            case 'product/created':
            case 'product/updated':
                $record = Product::with(['sku_in_channel' => function ($q) use ($channelId) {
                                $q->where('channel_sku.channel_id', '=', $channelId);
                            },
                            'product_category' => function($q) use ($channelId) {
                                $q->where('product_third_party_categories.channel_id','=',$channelId);
                            }
                            ,'media', 'brand'])->find($refId);

                if (is_null($record)) break;

                $channel_sku = ChannelSKU::where('product_id', '=', $refId)->groupBy('channel_id')->get();
                $data  = ['topic'=>$event,'content'=>$record->toAPIResponse()];
                
            break;
            
            case 'sku/created':
            case 'sku/updated':
                $record = SKU::findOrFail($refId);
                $channel_sku = ChannelSKU::where('sku_id', '=', $refId)
                                ->where('channel_id','=',$channelId)->first();
                $data = array();
                   
                // foreach ($channel_sku as $cs) 
                // {
                    $data  = ['topic'=>$event,'content'=>$channel_sku->toAPIResponse()];
                // }
                             
            break;
            
            case 'media/created':
            case 'media/updated':
            case 'media/deleted':
                $record = ProductMedia::withTrashed()->findOrFail($refId);
                $product = Product::with('media', 'brand')->findOrFail($record->product_id);
                $channel_sku = ChannelSKU::where('product_id', '=', $product->id)->groupBy('channel_id')->get();
                $data  = ['topic'=>$event,'content'=>$record->toAPIResponse()];
                
            break;
            
            case 'sales/created':
            case 'sales/updated':
                $record = Order::with('items', 'member')->findOrFail($refId);
                $data  = ['topic'=>$event,'content'=>$record->toAPIResponse()];
            break;

            default:
               $data = ['error'=>true, 'message'=>'This event is invalid.'];
        }

        return $data;
    }

    public function validateWebhookEvent($event, $refId, $channelId)
    {
        // cater for sales, product, sku, media
        $event = explode('/', $event);
        $valid = false;
        switch ($event[0]) {
            case 'orders':
            case 'sales':
                $sales = DB::table('orders')->where('id', '=', $refId)->where('channel_id', '=', $channelId);
                if($sales->count()){
                    $valid = true;
                }
                break;
            case 'product':
                $channelSku = DB::table('channel_sku')->where('product_id', '=', $refId)->where('channel_id', '=', $channelId);
                if($channelSku->count()){
                    $valid = true;
                }
                break;
            case 'sku':
                $channelSku = DB::table('channel_sku')->where('sku_id', '=', $refId)->where('channel_id', '=', $channelId);
                if($channelSku->count()){
                    $valid = true;
                }
                break;
            case 'media':
                $media = DB::table('product_media')->leftjoin('products', 'products.id', '=', 'product_media.product_id')
                                                ->leftjoin('channel_sku', 'channel_sku.product_id', '=', 'products.id')
                                                ->where('product_media.id', '=', $refId);
                if($media->count()){
                    $valid = true;
                }
                break;
            default:
               $valid = true;
        }
        
        return $valid;
    }

    public function getWebhookUrls(Request $request)
    {
        $clientId = $request->input('clientId');
        $clientSecret = $request->input('clientSecret');
        $oauthClients = DB::table('oauth_clients')->where('id', '=', $clientId)->where('secret', '=', $clientSecret)->first();
        $webhookUrls = DB::table('webhooks')->where('channel_id', '=', $oauthClients->authenticatable_id)->where('type','=',1)->get();

        return response()->json($webhookUrls);
    }

    public function getAccessToken(Request $request)
    {
        $clientId = $request->input('clientId');
        $clientSecret = $request->input('clientSecret');
        $header = ['Content-Type' => 'application/json'];
        $body = '
        {
            "client_id"         : "' . $clientId . '",
            "client_secret"     : "' . $clientSecret . '",
            "grant_type"        : "client_credentials"
        }';

        $response = $this->guzzleRequest('POST', $this->accessTokenUrl, $header, $body);

        return $response;
    }

    private function guzzleRequest($type = 'GET', $url, $header, $body = '')
    {
        $guzzle = new Guzzle();
        try{
            $request = $guzzle->request($type, url($url), [
                'headers' => $header, 
                'body' => $body,
            ]);
            return $request->getBody()->getContents();
        }
        catch(RequestException $e)
        {
            return $e->getResponse()->getBody()->getContents();
        }
        catch(ClientException $e)
        {
            return $e->getResponse()->getBody()->getContents();
        }
        catch(\Exception $e)
        {
            return response()->json(['error'=>true, 'message'=>$e->getMessage()]);
        }

    }
    
}
