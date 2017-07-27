<?php
namespace App\Modules\ThirdParty\Http\Controllers;
use App\Http\Controllers\Admin\AdminController;
use GuzzleHttp\Exception\RequestException as RequestException;
use GuzzleHttp\Client as Guzzle;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;

use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;
use Exception;

use App\Services\Mailer;

class StorefrontVendorController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $sync;
	public $limit,$exceed;
    private $error_data = array();

    public function __construct()
    {
    	$this->limit = 5;
    	$this->exceed = false;
    	$this->api_name = 'StorefrontVendor';
    	set_exception_handler(array($this, 'exceptionHandler'));
    	
    }
    public function exceptionHandler($e) {
		$this->error_data['Line'] = $e->getLine();
		$this->error_data['ErrorDescription'] = $e->getMessage();
		if(!empty($this->sync)) {
            $this->sync->status = 'FAILED';
            $this->sync->remarks = $e->getMessage();
            $this->sync->save();
            $this->error_data['Sync'] = $this->sync->toArray();
        }
		\Log::info(__CLASS__.'(' . $e->getLine() . ') >> Error: ' . $e->getMessage());
	}

    public function errorHandler($function, $line, $response, $message = '') {
        if(!empty($response)) {
            $this->error_data['response'] = $response;
        }

        $class = explode('\\', get_class($this));
        $class_name = $class[count($class)-1];
        $error['subject'] = 'Error '. $class_name;
                $error['File'] = __FILE__;
        $error['Function'] = $function;
        $error['Line'] = $line;
        $error['ErrorDescription'] = $message;
        $this->error_data = $error + $this->error_data;

        if(!empty($this->sync)) {
		$this->limit = 5;
		$this->exceed = false;
        	if($this->sync->retries >= ($this->limit-1)) $this->exceed = true;

            $this->sync->status = $this->exceed?'FAILED':'RETRY';
            $this->sync->retries = !$this->exceed?($this->sync->retries+1):0;
            $this->sync->remarks = $message;
            $this->sync->save();
            $this->error_data['Sync'] = $this->sync->toArray();

            if(!empty($this->webhook) && $this->exceed)
        	{
        		// Get channel email to send notification
        		$channel = Channel::with('channel_detail')->find($this->webhook->channel_id);
        		if(!empty($channel) && !empty($channel->channel_detail->support_email))
        		{
		         	$mailer = new Mailer;
		         	$data['limit'] = $this->limit;
		         	$data['title'] = 'WebHook Failed Notification : '.$channel->name;
			        $data['to'] = [$channel->channel_detail->support_email];
			        $data['cc'] = ['jun@hubwire.com'];
			        $data['channel'] = $channel->name;
			        $data['webhook'] = $this->webhook;
			        $data['response'] = json_encode($response);
			        $mailer->WebHookFailedNotification($data);
			    }
		    }
        }

        $this->ErrorAlert($this->error_data);
        return false;
    }

	public function initialize($channel, ThirdPartySync $sync = null)
    {
        $this->sync = is_null($sync) ? null : $sync;
        return $this;
    }

    private function getWebhook($event = '')
    {

    	if($event=='' && !is_null($this->sync))
    	{
            switch($this->sync->action)
    		{    
                case 'createProduct':
    				$event = 'product/created';
    				break;
    			case 'updateProduct';
                case 'uploadNewMedia':
                case 'setDefaultMedia':
                case 'updateMedia':
                case 'deleteMedia':
    				$event = 'product/updated';
    				break;
    			case 'createSKU':
    				$event = 'sku/created';
    				break;
    			case 'updateQuantity':
                case 'updateSKU':
    				$event = 'sku/updated';
    				break;
                case 'orderCreated':
                    $event = 'sales/created';
                    break;
                case 'orderUpdated':
                    $event = 'sales/updated';
                    break;
    			default:
    				$event = '';
    			break;
    		}
    	}
    	if(trim($event)==='')
    	{
    		$message = 'Unrecognised event.';
    		$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
    		return ['error' => true, 'message' => $message];
        }
    	$webhook = Webhook::where('channel_id', '=', $this->sync->channel_id)
                    ->where('topic', '=', $event)
                    ->where('type', '=', 1)
                    ->first();
        if(is_null($webhook))
        {
        	$message = 'There is no webhook found for this particular event.';
			return ['error' => true, 'message' => $message];
  		}
		return $webhook;
    }

    private function pushProduct($product)
    {
        $webhook = $this->getWebhook();
    	
        if(!empty($webhook['error']) && $webhook['error'])
    	{
    		$error = array();
        	$message = $webhook['message'];
			$error['error_desc'] = $message;
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			
        	$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
        	return MpUtils::errorResponse($error, __METHOD__, __LINE__);
    	}
    	$record = Product::with(['sku_in_channel' => function ($q) {
                                $q->where('channel_sku.channel_id', '=', $this->sync->channel_id);
                            },
                            'product_category' => function($q){
                                $q->where('product_third_party_categories.channel_id','=',$this->sync->channel_id);
                            }
                            ,'media', 'brand'])->find($product['id']);
        
        if (is_null($record))
        {
        	$error = array();
        	$message = 'Product not found.';
			$error['error_desc'] = $message;
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			
        	$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
        	return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
        if (empty($record->product_category))
        {
            $error = array();
            $message = 'ARC Error: Product must be assigned with a category';
            $error['error_desc'] = $message;
            $error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
            
            $this->errorHandler(__FUNCTION__, __LINE__, null, $message);
            return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }

        $request = new \stdClass();
        $request->webhook = $webhook;
        $request->data  = ['topic'=>$webhook->topic,'content'=>$record->toAPIResponse()];
        return $this->sendResponse($request);
        
    }

    private function pushSKU($sku)
    {
    	$webhook = $this->getWebhook();

    	if(!empty($webhook['error']) && $webhook['error'])
    	{
    		$error = array();
        	$message = $webhook['message'];
			$error['error_desc'] = $message;
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			
        	$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
        	return MpUtils::errorResponse($error, __METHOD__, __LINE__);
    	}
    	
    	$record = ChannelSKU::with('sku', 'sku_options', 'tags')
    			->where('sku_id','=',$sku['sku_id'])
                ->where('channel_id','=',$this->sync->channel_id)
                ->first();

        if (is_null($record))
        {
        	$error = array();
        	$message = 'SKU not found.';
			$error['error_desc'] = $message;
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');

        	$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
        	return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }

        $request = new \stdClass();
        $request->webhook = $webhook;
        $request->data  = ['topic'=>$webhook->topic,'content'=>$record->toAPIResponse()];
        return $this->sendResponse($request);
        
    }

    public function pushOrder($order)
    {
        $webhook = $this->getWebhook();

        if(!empty($webhook['error']) && $webhook['error'])
        {
            $error = array();
            $message = $webhook['message'];
            $error['error_desc'] = $message;
            $error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
            
            $this->errorHandler(__FUNCTION__, __LINE__, null, $message);
            return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
        
        $record = Order::with('items', 'member')
                ->find($this->sync->ref_table_id);

        if (is_null($record))
        {
            $error = array();
            $message = 'Order not found.';
            $error['error_desc'] = $message;
            $error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');

            $this->errorHandler(__FUNCTION__, __LINE__, null, $message);
            return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }

        $request = new \stdClass();
        $request->webhook = $webhook;
        $request->data  = ['topic'=>$webhook->topic,'content'=>$record->toAPIResponse()];
        
        return $this->sendResponse($request);
    }

    public function createProduct(array $product, $bulk = false)
    {
        $response = $this->pushProduct($product);
    	if($response['success'])
        {
            $productThirdParty = array(
                'ref_id' => $product['id'],
                'channel_id' => $this->sync->channel_id,
                'third_party_name' => $this->api_name,
                'product_id' => $product['id']
            );
            
            ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

            $channel_skus = ChannelSKU::where('product_id','=',$product['id'])
                            ->where('channel_id','=', $this->sync->channel_id)
                            ->get();

            foreach($channel_skus as $channel_sku) {
                $sku = array(
                    'hubwire_sku'   => $channel_sku->sku->hubwire_sku,
                    'merchant_id'   => $channel_sku->merchant_id,
                    'channel_id'    => $this->sync->channel_id,
                    'ref_id'        => $channel_sku->channel_sku_id,
                    'product_id'    => $channel_sku->product_id
                );

                $storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($sku);
            }
        }
    }

    public function updateProduct(array $product, $bulk = false)
    {
       $this->pushProduct($product);
    }

    public function deleteProduct(array $product, $bulk = false)
	{
		$this->pushProduct($product);
	}

    public function createSku(array $sku, $bulk = false) 
    {
        $response = $this->pushSKU($sku);
        if($response['success'])
        {
            $channel_sku = ChannelSKU::where('sku_id','=',$sku['sku_id'])
                            ->where('channel_id','=', $this->sync->channel_id)
                            ->first();

            $sku = array(
                'hubwire_sku'   => $channel_sku->sku->hubwire_sku,
                'merchant_id'   => $channel_sku->merchant_id,
                'channel_id'    => $this->sync->channel_id,
                'ref_id'        => $channel_sku->channel_sku_id,
                'product_id'    => $channel_sku->product_id
            );

            $storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($sku);
        }
    }

    public function updateSku(array $sku, $bulk = false) 
    {
       $this->pushSKU($sku);
    }

    public function updateQuantity(array $sku, $bulk = false) 
    {
        return $this->updateSKU($sku, $bulk);
    }

    public function deleteSku(array $sku, $bulk = false) 
    {
    	return $this->updateSKU($sku, $bulk);
    }

    public function updateImages(array $images) 
    {
        $product = ['id'=>$images['product_ref']];
    	return $this->updateProduct($product);
    }

	public function getOrders(array $filters) 
	{

	}

    public function orderCreated(array $order)
    {
        return $this->pushOrder($order);
    }

    public function orderUpdated(array $order)
    {
        return $this->pushOrder($order);
    }

	public function sendResponse($request){
		try 
		{
			$this->webhook = $request->webhook;
			$client = new Guzzle;
	        $postman = $client->request('POST', $request->webhook->address, [
	            'json'=>$request->data
	        ]);

	        $response = json_decode($postman->getBody()->getContents());
	        $this->sync->remarks = !empty($response->message)?$response->message:'OK (200)';
	    	$this->sync->status = 'SUCCESS';
	    	$this->sync->save();
            return ['success'=> true];
    	}
    	catch(RequestException $e)
    	{
            if ($e->hasResponse()) {
        		$response = json_decode($e->getResponse()->getBody()->getContents());
        		$this->errorHandler(__FUNCTION__, __LINE__, $response, !empty($response->message)?$response->message:$e->getMessage());
            }
            else{
                $this->errorHandler(__FUNCTION__, __LINE__, null, $e->getMessage() );
            }
            return ['success'=> false];
    	}
    	catch(\Exception $e)
    	{
    		$this->errorHandler(__FUNCTION__, __LINE__, null, $e->getMessage());
            return ['success'=> false];
    	}

	}

	public function updateVisibility(array $product, $bulk = false)
	{
		return $this->updateProduct($product, $bulk);
	}

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function readyToShip($input)
    {
        return array('success'=>true, 'tracking_no'=>$input['tracking_no']);
    }
}
