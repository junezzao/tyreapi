<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Controllers\Admin\AdminController;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Repositories\ShopifyRepo;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Config;
use App\Repositories\Eloquent\SyncRepository;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\Order;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\ChannelSKU;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;
use Monolog;
use Input;
use Response;
use Request;
use URL;
use Carbon\Carbon;
use Image;
use App\Services\MediaService as MediaService;

class ShopifyController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $__api, $customLog, $sync, $order;
	private $error_data = array();
	private $livePriceSkus = array();

	public function __construct() {
		$this->api_name = get_class($this);

		$this->customLog = new Monolog\Logger('Shopify Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/shopify.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error Shopify';
		$this->error_data['File'] = __FILE__;

		set_exception_handler(array($this, 'exceptionHandler'));
	}

	public function exceptionHandler($e) {
		$this->error_data['Line'] = $e->getLine();
		$this->error_data['ErrorDescription'] = $e->getMessage();

		\Log::info('ShopifyController (' . $e->getLine() . ') >> Error: ' . $e->getMessage());
	}

	public function errorHandler($function, $line, $response, $message = '') {
		if(!empty($response)) {
			$this->error_data['response'] = $response;
		}

		$this->error_data['Function'] = $function;
		$this->error_data['Line'] = $line;
		$this->error_data['ErrorDescription'] = $message;

		if(!empty($this->sync)) {
			$this->sync->status = 'FAILED';
			$this->sync->remarks = json_encode($message);
			$this->sync->save();
			$this->error_data['Sync'] = $this->sync->toArray();
		}

		$this->ErrorAlert($this->error_data);
	}

	public function initialize(Channel $channel, ThirdPartySync $sync = null)
	{
		$this->channel = $channel;

		$channel_type = $this->channel->channel_type->name;
        $this->channel->isPos = ($channel_type == 'Shopify POS') ? true : false;

		$this->__setApi($channel);
		$this->sync = is_null($sync) ? null : $sync;
	}

	private function __setApi(Channel $channel) {
		$url = parse_url($this->channel->website_url);

		//php 5.4 bug for missing scheme
		if (!isset($url['scheme']))
		{
			$url = parse_url("http://" . $this->channel->website_url);
		}

		$this->channel = $channel;
		$this->__api = $this->client($url['host'], NULL, $this->channel->channel_detail->api_key, $channel->channel_detail->api_password, $channel->channel_detail->api_secret);
	}

	public function api() {
		return $this->__api;
	}

	private function client($shop, $shops_token, $api_key, $shared_secret, $private_app = false)
	{
		$password = $shops_token;
		$baseurl = $private_app ? "https://$api_key:$shared_secret@$shop/" : "https://$shop/";

		return function ($method, $path, $params = array(), &$response_headers = array()) use ($baseurl, $shops_token)
		{
			$url = $baseurl . ltrim($path, '/');
			$query = in_array($method, array('GET', 'DELETE')) ? $params : array();
			$payload = in_array($method, array('POST', 'PUT')) ? json_encode($params): '';

			$logInfo = "";
			$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
			$logInfo .= "Request Body/Query: ";
			$this->customLog->addInfo($logInfo, $params);

			return $this->_api($method, $url, $query, $payload, $shops_token, $response_headers);
		};
	}

	private function _api($method, $url, $query = '', $payload = '', $shops_token = '', &$response_headers = array()) {
		$guzzle = new Client();
		$this->error_data['DateSent'] = array('p' => $payload,'q' => $query);

		$requestHeaders['X-Shopify-Access-Token'] = $shops_token;
		if (in_array($method, array('POST', 'PUT'))) $requestHeaders["Content-Type"] = "application/json";
		$request = new GuzzleRequest($method, $url, $requestHeaders);

		try {
			$response = $guzzle->send($request, array('body' => $payload, 'query' => $query, 'http_errors' => false));
		}
		catch (RequestException $e) {
			$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;

			$response = $e->getResponse();
			$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';

			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

			if ($e->hasResponse()) {
				$this->customLog->addError(json_encode($e->getResponse()));
		    }

			return false;
		}
		catch (Exception $e) {
			$message = "Error send request. Unknown error. Request URL: " . $url;
			$message .= ' Error: ' . $e->getMessage() . ' at line ' . $e->getLine();

			$this->customLog->addError($message);

		    $this->errorHandler(__FUNCTION__, $e->getLine(), (!empty($response) ? $response : null), $message);
			return false;
		}

		$response_headers['http_status_code'] = !empty($response) ? $response->getStatusCode() : 400;
		$response_headers['status'] = 'No status';

		$headers = !empty($response) ? $response->getHeaders() : array();
		foreach($headers as $name => $values ) {
			$response_headers[strtolower($name)] = implode(', ', $values);
		}

		$response = json_decode($response->getBody()->getContents(), true);

		if (isset($response['errors']) or ($response_headers['http_status_code'] >= 400)) {
			$message = !empty($response_headers['x-stats-validation-errors']) ? $response_headers['x-stats-validation-errors'] : $response_headers['status'];
			$message .= " | Error: " . json_encode($response['errors']);
			$message .= " | Request URL: " . $url;
			$this->error_data['header'] = $response_headers;
			$this->error_data['message'] = json_encode($response['errors']);
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);

			return false;
		}
		else {
			return (is_array($response) and !empty($response)) ? array_shift($response) : $response;
		}
	}

	public function sendResponse($response) {}

	/*
		Process Input from Shopify Webhooks and verify Webhook Signature
	*/
	public function receiver() {
		try{
			//get domain
			$domain = Request::header('x-shopify-shop-domain');
			if(empty($domain)){
				return array('verified' => false, 'channel' => '');
			}

			//get channel by domain
			$channel_type = array();
			$channel_type = ChannelType::whereIn('name', ['Shopify', 'Shopify POS'])->get(['id']);
			foreach ($channel_type as $value) {
        		$channel = Channel::with('channel_detail')->where('channel_type_id', $value->id)->where('website_url', $domain)->first();
        		if (!empty($channel)) {
        			break;
        		}
			}
			$this->channel = $channel;
			return array('verified' => true, 'channel' => $channel);
		}
		catch(Exception $e){
			$error = array();
			$error['error_desc'] = "RequestException : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public function registerWebhooks() {
		try{
			$this->customLog->addInfo('Shopify - channel ' . $this->channel->id . ' >> getting registered webhooks');

			$shopify = $this->api();
			$response = $shopify('GET', '/admin/webhooks.json', array());

			if($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->customLog->addInfo('Shopify - channel ' . $this->channel->id . ' >> registered webhooks: ' . json_encode($response));
			$this->customLog->addInfo('Shopify - channel ' . $this->channel->id . ' >> deleting registered webhooks');

			if (!empty($response) && count($response) > 0) {
				foreach($response as $webhook) {
					$responseDelete = $shopify('DELETE', '/admin/webhooks/' . $webhook['id'] . '.json', array());

					if($responseDelete === false){
						$this->error_data['channel'] = $this->channel;
						$this->error_data['ErrorDescription'] = $responseDelete['errorMsg'];

						return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
					}
				}
			}

           $channel_type = $this->channel->channel_type->name;
            //by default is Pos unless store_type isset and stated as Webstore
            $isPos = ($channel_type == 'Shopify POS') ? true : false;

            $webhooks = array(
            	array(
            		'topic' 	=> 'orders/create',
            		'address'	=> env('APP_URL').'/1.0/webhook/shopify/order/create',
            		'format'	=> 'json'
            	),
            	array(
            		'topic'		=> 'orders/updated',
            		'address'	=> env('APP_URL').'/1.0/webhook/shopify/order/update',
            		'format'=>'json'
            	),
            	array(
            		'topic' 	=> 'orders/cancelled',
            		'address'	=> env('APP_URL').'/1.0/webhook/shopify/order/cancel',
            		'format'	=> 'json'
            	)
            );

			$this->customLog->addInfo('Shopify - channel ' . $this->channel->id . ' >> registering webhooks');
			$responses = array();
			foreach($webhooks as $webhook) {
	            $response = $shopify('POST', '/admin/webhooks.json', array('webhook' => $webhook));

	            if($response === false) {
					$this->error_data['channel'] = $this->channel;
					$this->error_data['ErrorDescription'] = $response['errorMsg'];

					$this->customLog->addInfo('Shopify - channel ' . $this->channel->id . ' >> error registering webhook topic ' . $webhook['topic'] . ' | error: ' . $response['errorMsg']);

					return Response::JSON(array('success' => false, 'error' => $this->error_data, 'responses' => $responses), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				}

				$responses[] = $response;
			}

			return Response::JSON(array('success' => true, 'responses' => json_encode($responses)), MpUtils::getStatusCode('OK_STATUS'));
		}
		catch(Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}
	}

	public function setOrder($order)
	{
		$this->order = $order;
	}

	/*
	 *	Webhook Events
	 *	@param $data relavant information retrieved from database
	 */
	public function order_create($data) {
		$this->customLog->addInfo('Shopify.order_create >> Order Id: '. $data['id']);
		$this->customLog->addInfo('Shopify.order_create >> data: '. json_encode($data, true));

		$channel = Channel::where('id', $this->channel->id)->first();
		$response = ShopifyRepo::processOrder($data, $channel);

		if($response['success']) {
			$order_proc = new OrderProc($this->channel->id, new $this->channel->channel_type->controller);
	    	$resp = $order_proc->createOrder($this->channel->id, $response);
	      	return $resp;
	    } else {
	    	return $response;
	    }
	}

	/*
		This event is triggered by shopify every time there is changes in the order
		during order creation/payment/fulfillment/canceled/refund
	*/
	public function order_update($data) {
		return $this->update($data, __FUNCTION__);
	}

	public function order_payment($data) {
		// do nothing to prevent overlapped processing - order/updated event is sent together with this event
		$response['success'] = true;
		return Response::JSON($response, 200);
	}

	public function order_cancel($data) {
		return $this->update($data, __FUNCTION__);
	}

	public function order_fulfill($data) {
		// so far this event will not be sent over, order fulfillment will send the orders/updated event
		$response['success'] = true;
		return Response::JSON($response, 200);
	}

	public function order_partial_fulfill($data) {
		// so far this event will not be sent over, order fulfillment will send the orders/updated event
		$response['success'] = true;
		return Response::JSON($response, 200);
	}

	//assumption refund does not take place if payment is not confirmed
	public function refund_create($data) {
		// do nothing to prevent overlapped processing - order/updated event is sent together with this event
		$response['success'] = true;
		return Response::JSON($response, 200);
	}

	private function update($data, $function) {
		$this->customLog->addInfo("webhook.$function >> tp_order_id: " . $data['id'] . " | data: " . json_encode($data));

		$order = Order::where('tp_order_id', $data['id'])->where('channel_id', $this->channel->id)->first();
		if(empty($order)) return Response::JSON(array('success'=>true), 200);

		$response = ShopifyRepo::processOrderUpdateRequest($data, $order, $this->channel->channel_type->name);

		if($response['success']) {
			if($order->getStatusCode() <= Order::$pendingStatus || $this->channel->channel_type->name = 'Shopify POS') {
				return $order->updateOrder($response);
		    } else {
		    	return Response::JSON(array('success'=>true), 200);
		    }
	    }
	    else {
	    	$this->error_data['ErrorDescription'] = 'Error ['.$response['status_code'].'] '.$response['error_desc'].' in '.$response['method'].' at line '.$response['line'];
            $this->customLog->addError($this->error_data['ErrorDescription']);
            return Response::JSON(array('success'=>false), 404);
	    }
	}

	// WEBHOOK EVENTS - END

    public function setSalesStatus($order_id, array $details) {}

    private function checkOrderItemsForRTS($tpOrderId, array $tpItems) {
    	$order = $this->getSingleOrder($tpOrderId, true);

    	$rtsItems = [];
    	if (!empty($order['refunds'])) {
    		foreach($tpItems as $tpItem) {
    			$rtsItems[$tpItem['id']]['quantity_to_fulfill'] = $tpItem['quantity'];
    		}

    		foreach ($order['line_items'] as $orderItem) {
    			if (!empty($rtsItems[$orderItem['id']])) {
    				$rtsItems[$orderItem['id']]['quantity_available'] = $orderItem['quantity'];
    			}
    		}

    		foreach ($order['refunds'] as $refund) {
    			foreach ($refund['refund_line_items'] as $item) {
    				if (!empty($rtsItems[$item['line_item_id']])) {
    					$rtsItems[$item['line_item_id']]['quantity_available'] -= $item['quantity'];
    				}
    			}
    		}

    		foreach ($rtsItems as $tpItemId => $itemInfo) {
    			if ($itemInfo['quantity_available'] < $itemInfo['quantity_to_fulfill']) {
    				return ['success' => false, 'error' => 'All ready-to-ship items must not be Restocked/Refunded in Shopify. Please check that the quantity for ready-to-ship does not exceed the available quantity of each item.'];
    			}
    		}
    	}

    	return ['success' => true];
    }

    /**
	 * Creates a fulfillment for the specified order
	 *
	 * @param  object sync
	 */
	public function readyToShip($input)
	{
		try{
			$extra_info = json_decode($this->channel->channel_detail->extra_info, true);

			if (empty($extra_info['shipping_provider'])) {
				return ['success' => false, 'message' => 'Please set a shipping provider for the channel.'];
			}

			$data['fulfillment']['tracking_number'] = $input['tracking_no'];
			$data['fulfillment']['tracking_urls'][] = Config::get('shopify.default.'.$extra_info['shipping_provider'].'.tracking_url');
			$data['fulfillment']['tracking_company'] = Config::get('shopify.default.'.$extra_info['shipping_provider'].'.tracking_company');
			$data['fulfillment']['notify_customer'] = true;

			$items  = $this->order->items->groupBy('tp_item_id');

			$tp_items  = array();
			foreach($items as $tp_item_id => $group)
			{
				foreach($group as $item)
				{
					if(strcasecmp($item->status,'verified') == 0 || strcasecmp($item->status,'picked') == 0 )
					{
						$tp_items[$tp_item_id]['id'] = $tp_item_id;
						$tp_items[$tp_item_id]['quantity'] = (!empty($tp_items[$tp_item_id]['quantity'])?$tp_items[$tp_item_id]['quantity']:0) + $item->quantity;
					}
				}
			}

			if(!empty($tp_items))
			{
				foreach($tp_items as $tp_item){
					$data['fulfillment']['line_items'][] = $tp_item;
				}
			}
			else {
				return ['success' => false, 'message' => 'No items to ship.'];
			}

			$check = $this->checkOrderItemsForRTS($this->order->tp_order_id, $tp_items);

			if (!$check['success']) {
				return ['success' => false, 'message' => $check['error']];
			}

			$shopify = $this->api();
			$this->customLog->addInfo('ReadyToShip sending Guzzle request... '. json_encode($data));
			$response = $shopify('POST', '/admin/orders/'. $this->order->tp_order_id .'/fulfillments.json', $data);
			$this->customLog->addInfo('ReadyToShip response... '. json_encode($response));
			if($response === false){
				return array('success'=>false, 'message'=>$this->error_data['message']);
			}
		}
		catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}

		return array('success'=>true, 'tracking_no'=>$input['tracking_no']);
	}

	/**
	 * To create refund for an item from hubwire and sync to Shopify
	 *
	 * Calculate a refund for a line item,
	 * then Create a new refund for an order
	 *
	 * POST /admin/orders/#{id}/refunds/calculate.json
	 * POST /admin/orders/#{id}/refunds.json
	 *
	 * @param  object sync
	 */
	public function createItemRefund($info) {
		try{
			$data['refund']['refund_line_items'][0]['line_item_id'] = $info['item_ref'];
			$data['refund']['refund_line_items'][0]['quantity'] = $info['quantity'];

			$shopify = $this->api();
			$calculatedRefund = $shopify('POST', '/admin/orders/' . $info['order_ref'] . '/refunds/calculate.json', $data);

			if($calculatedRefund === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$refundData = array();

			if (empty($calculatedRefund['refund'])) {
				$refundData['refund'] = $calculatedRefund;
			}
			else {
				$refundData = $calculatedRefund;
			}

			foreach ($refundData['refund']['transactions'] as $key => $value) {
				$refundData['refund']['transactions'][$key]['kind'] = "refund";

				if (!$info['refund_applicable']) {
					$refundData['refund']['transactions'][$key]['amount'] = 0;
				}
			}

			$refundData['refund']['restock'] = $info['restock'];
			$refundData['refund']['note'] = $info['remark'];
			$response = $shopify('POST', '/admin/orders/' . $info['order_ref'] . '/refunds.json', $refundData);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$refunded_amount = 0;
			if (empty($response['refund'])) {
				$response['refund'] = $response;
			}

			if ($info['refund_applicable']) {
				foreach ($response['refund']['transactions'] as $transaction) {
					$refunded_amount += $transaction['amount'];
				}
			}

			$info['refunded_amount'] = $refunded_amount;
			$info['refund_ref'] = $response['refund']['id'];
			$info['updated_at'] = $response['refund']['created_at'];

			ShopifyRepo::processSyncRefund($info);
			return Response::JSON(array('success' => true, 'response' => json_encode($response)), MpUtils::getStatusCode('OK_STATUS'));
		}
		catch (Exception $e) {
			$message = 'Error: ' . $e->getMessage() . ' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}
	}

    public function getOrders(array $filters) {
    	$shopify = $this->api();

    	$hasOrders = true;
		$page = 1;
		$results = array();

		while ($hasOrders) {
			$filters['page'] = $page;

			$response = $shopify('GET', '/admin/orders.json', $filters);

			if (empty($response) || count($response) == 0) {
				$hasOrders = false;
				continue;
			}

			foreach ($response as $order) {
				$results[] = $this->order_create($order);
			}

			$page++;
		}

		return $results;
    }

	public function getSingleOrder($order_code, $asRaw = false) {
		$shopify = $this->api();

		$return = array();
		$response = $shopify('GET', '/admin/orders/'.$order_code.'.json');

		if (empty($response) || count($response) == 0) {
			$error['error_desc'] = "Invalid Shopify Order Number";
			$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
			$return['response'] =  MpUtils::errorResponse($error, __METHOD__, __LINE__);
		} else {
			$channel = Channel::where('id', $this->channel->id)->first();

			if ($asRaw) {
				return $response;
			}
			else {
				$return['response'][$order_code] = ShopifyRepo::processOrder($response, $channel);
			}
		}

		return $return;
	}

	/*
	 *
	 * Product/SKU
	 *
	 */
	public function createProduct(array $product, $bulk = false) {
		try{
			$data = $this->prepareProduct($product);
            if($data === false){
            	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

			$shopify = $this->api();
			$response = $shopify('POST', '/admin/products.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$pRefId = $response['id'];

			// Validate the response
			if( empty($pRefId) || $pRefId==0 || empty($response['variants']) ){
				$message = 'Expected Response Element Not Found: Product Id/Variants | data sent: ' . json_encode($data);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);

				$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$productThirdParty = array(
				'ref_id' => $pRefId,
				'channel_id' => $this->channel->id,
				'third_party_name' => 'Shopify',
				'product_id' => $this->sync->ref_table_id
			);

			ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

			// Store SKU third party information for later reference
			foreach($response['variants'] as $variant){
				// Validate the response
				if(empty($variant['id']) || $variant['id'] == 0){
					$message = 'Expected Response Element Not Found: Variant Id | data sent: ' . json_encode($data);
					$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				}

				$sku = array(
					'hubwire_sku'	=> $variant['sku'],
					'merchant_id'	=> $product['merchant_id'],
					'channel_id'	=> $this->channel->id,
					'ref_id'		=> $variant['id'],
					'product_id'	=> $product['id']
				);

				$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($sku);

				if(!$storeResponse){
					$message = 'Expected Response Not Match With Hubwire Model: SKU ' . $variant['sku'] . ' | data sent: ' . json_encode($data);
					$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				}
			}

			$hideProductStatus = $this->hideProduct($pRefId, $product['id']);
			if($hideProductStatus === true) {
				$this->sync->status = 'SUCCESS';
				$this->sync->save();
			}

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);

			// Create a new sync for image upload
			$input['product_id'] = $this->sync->ref_table_id;
			$input['channel_id'] = $this->channel->id;
			$input['merchant_id'] = $product['merchant_id'];
			$input['trigger_event'] = $this->sync->trigger_event;
			$this->customLog->addInfo("Creating sync job for image upload. ", $input);
			$syncRepo = new SyncRepository;
			$newSync = $syncRepo->updateMedia($input);
			$this->customLog->addInfo("New sync created. | " . json_encode($newSync));
		}
		catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}
	}

    public function updateProduct(array $product, $bulk = false) {
    	try {
    		$data = $this->prepareProduct($product, false);
			if($data === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

			$shopify = $this->api();
			$response = $shopify('PUT', '/admin/products/' . $product["product_ref"] . '.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);

			$hideProductStatus = $this->hideProduct($product['product_ref'], $product['id']);
			if($hideProductStatus === true) {
				$this->sync->status = 'SUCCESS';
				$this->sync->save();
			}

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function updatePrice(array $sku, $bulk = false) {
    	try {
    		$price = $this->preparePrice($sku, $sku['remove_gst']);
			if($price === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

			$data['variant'] = array(
				'price' 			=> $price,
				'compare_at_price'  => ($price < $sku['unit_price']) ? $sku['unit_price'] : 0
			);
    		$shopify = $this->api();
			$response = $shopify('PUT', '/admin/variants/' . $sku['channel_sku_ref'] . '.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function deleteProduct(array $product, $bulk = false) {
    	try {
			$shopify = $this->api();
			$response = $shopify('DELETE', '/admin/products/' . $product["product_ref"] . '.json', array());

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			//delete third party's media info
			foreach ($product['images'] as $image) {
				if (!empty($image['image_ref'])) {
					$mediaThirdParty = ProductSyncRepo::getProductMediaThirdParty($image['id'], $this->channel->id);
					$mediaThirdParty->delete();
				}
			}

			foreach ($product['deleted_images'] as $image) {
				if (!empty($image['image_ref'])) {
					$mediaThirdParty = ProductSyncRepo::getProductMediaThirdParty($image['id'], $this->channel->id);
					$mediaThirdParty->delete();
				}
			}

			//delete third party's product info
			$productThirdParty = ProductSyncRepo::getProductThirdParty($product['id'], $this->channel->id);
			$productThirdParty->delete();

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function createSku(array $sku, $bulk = false) {
    	try {
    		$data = $this->prepareSku($sku);
            if($data === false){
            	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

    		$shopify = $this->api();
			$response = $shopify('POST', '/admin/products/' . $sku['product_ref'] . '/variants.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			if(empty($response['id']) || $response['id'] == 0){
				$message = 'Expected Response Element Not Found: Variant Id | data sent: ' . json_encode($data);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$skuInfo = array(
				'hubwire_sku'	=> $response['sku'],
				'merchant_id'	=> $sku['merchant_id'],
				'channel_id'	=> $this->channel->id,
				'ref_id'		=> $response['id'],
				'product_id'	=> $sku['product_id']
			);

			$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($skuInfo);

			if(!$storeResponse){
				$message = 'Expected Response Not Match With Hubwire Model: SKU ' . $response['sku'] . ' | data sent: ' . json_encode($data);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$hideProductStatus = $this->hideProduct($sku['product_ref'], $sku['product_id']);
			if($hideProductStatus === true) {
				$this->sync->remarks = json_encode($response);
				$this->sync->status = 'SUCCESS';
				$this->sync->save();
			}

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function updateSku(array $sku, $bulk = false) {
    	try {
			$data = $this->prepareSku($sku, false);
            if($data === false){
            	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

    		$shopify = $this->api();
			$response = $shopify('PUT', '/admin/variants/' . $sku['channel_sku_ref'] . '.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$hideProductStatus = $this->hideProduct($sku['product_ref'], $sku['product_id']);
			if($hideProductStatus === true) {
				$this->sync->remarks = json_encode($response);
				$this->sync->status = 'SUCCESS';
				$this->sync->save();
			}

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function updateQuantity(array $sku, $bulk = false) {
    	try {
			$data['variant'] = array(
				'inventory_quantity' 	=> ($sku['is_active']) ? $sku['quantity'] : 0
			);
    		$shopify = $this->api();
			$response = $shopify('PUT', '/admin/variants/' . $sku['channel_sku_ref'] . '.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$hideProductStatus = $this->hideProduct($sku['product_ref'], $sku['product_id']);
			if($hideProductStatus === true) {
				$this->sync->remarks = json_encode($response);
				$this->sync->status = 'SUCCESS';
				$this->sync->save();
			}
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function deleteSku(array $sku, $bulk = false) {
    	try {
    		$shopify = $this->api();
			$response = $shopify('DELETE', '/admin/products/' . $sku['product_ref'] . '/variants/' . $sku['channel_sku_ref'] . '.json', array());

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			if(!empty($response) && $response !== false) {
				$skuInfo = array(
					'hubwire_sku'	=> $sku['hubwire_sku'],
					'merchant_id'	=> $sku['merchant_id'],
					'channel_id'	=> $this->channel->id,
					'ref_id'		=> 0,
					'product_id'	=> $sku['product_id']
				);

				$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($skuInfo);
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function updateImages(array $data) {
    	try {
    		$shopify = $this->api();
			$dimensions = Config::get('marketplace.image_size.lg');
			$responses = array();

			foreach ($data['deleted_images'] as $image) {
				if (!empty($image['image_ref'])) {
					$response = $shopify('DELETE', '/admin/products/' . $data['product_ref'] . '/images/' . $image['image_ref'] . '.json', array());

					if($response === false){
						return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
					}

					$mediaThirdParty = ProductSyncRepo::getProductMediaThirdParty($image['id'], $this->channel->id);
					$mediaThirdParty->delete();

					$responses[] = $response;
				}
			}

			foreach ($data['images'] as $order => $image) {
				if (!empty($image['image_ref'])) { //image already exists in marketplace
					$tpData['image'] = array(
						'id' 		=> $image['image_ref'],
						'position' 	=> ($order + 1)
					);

					$response = $shopify('PUT', '/admin/products/' . $data['product_ref'] . '/images/' . $image['image_ref'] . '.json', $tpData);
				}
				else { //new image
					$image_data = base64_encode(file_get_contents($image['path'].$image['ext']));
					$tpData['image'] = array(
						'attachment' => $image_data,
						'filename' => $image['filename'].$image['ext'],
						'position' => ($order + 1)
					);

					$response = $shopify('POST', '/admin/products/' . $data['product_ref'] . '/images.json', $tpData);
				}

				if($response === false){
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				}

				if(!empty($response) && $response !== false) {
					if (empty($response['id']) || $response['id'] == 0) {
						$message = 'Expected Response Element Not Found: Media Id | data sent: ' . json_encode($data);
						$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
						return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
					}

					if (empty($image['image_ref'])) {
						$imageInfo = array(
							'media_id'			=> $image['id'],
							'ref_id'			=> $response['id'],
							'channel_id'		=> $this->channel->id,
							'third_party_name'	=> 'Shopify'
						);

						ProductSyncRepo::storeMediaThirdPartyInfo($imageInfo);
					}

					$responses[] = $response;
				}
			}

			$this->sync->remarks = json_encode($responses);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();
    	}
    	catch (Exception $e) {
    		$message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
    	}
    }

    public function updateVisibility(array $product, $bulk = false) {
    	$this->hideProduct($product['product_ref'], $product['sku_in_channel']);
    }

    /**
	 * Hide a product on Shopify.
	 * This function is not stand-alone, it will only be called by other sync jobs
	 */
	private function hideProduct($productRefId, $productId){
		try {
			$hasActiveSku = ProductSyncRepo::checkHasActiveChanneSku($this->channel->id, $productId);
			$data = array();

			if (!$hasActiveSku)
				$data['product']['published'] = false;
			else
				$data['product']['published'] = true;

			$shopify = $this->api();
			$response = $shopify('PUT', '/admin/products/' . $productRefId . '.json', $data);

			if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			return true;
		}
		catch(Exception $e){
			$message = 'Error: ' . $e->getMessage() . ' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
			return false;
		}
	}

    private function prepareProduct(array $product, $isCreate = true) {
    	$data['product'] = array(
			'title'			=> $product['name'],
			'body_html'		=> $product['description'],
			'vendor'		=> $product['brand'],
			'tags'			=> $product['tags'],
			'product_type'	=> $product['tags']
		);

    	if ($isCreate) {
    		$options = array();
	    	foreach ($product['options'] as $optionType => $optionValue) {
	    		$options[] = array("name" => $optionType, "values" => $optionValue);
	    	}
	    	$data['product']['options'] = $options;
    	}

		$j = 0;
		foreach ($product['sku_in_channel'] as $channel_sku) {
			$price = $this->preparePrice($channel_sku, $product['remove_gst']);
			if ($price === false) return false;

	        $data['product']['variants'][$j] = array(
				'sku'					=> $channel_sku['hubwire_sku'],
				'inventory_quantity' 	=> ($channel_sku['is_active']) ? $channel_sku['quantity'] : 0,
				'grams'					=> $channel_sku['weight'],
				'barcode'				=> $channel_sku['hubwire_sku'],
				'requires_shipping' 	=> ($this->channel->isPos) ? false : true,
				'price' 				=> $price,
				'compare_at_price' 		=> ($price < $channel_sku['unit_price']) ? $channel_sku['unit_price'] : 0
			);

			$i = 1;
			foreach($channel_sku['options'] as $optionType => $optionValue){
				if($i > 3) break;
				$data['product']['variants'][$j]["option$i"] = htmlspecialchars($optionValue);
				$i++;
			}

			if ($isCreate) {
 				$data['product']['variants'][$j]['inventory_policy'] = 'deny';
 				$data['product']['variants'][$j]['inventory_management'] = 'shopify';
 				$data['product']['variants'][$j]['title'] = '';
			}
			else {
 				$data['product']['variants'][$j]['id'] = $channel_sku['channel_sku_ref'];
			}

			if(!empty($channel_sku['custom_fields'])) {
 				foreach ($channel_sku['custom_fields'] as $key => $value) {
 					if (strcasecmp($key, 'variants') == 0) {
 						// nested level e.g. variants.grams
 						foreach ($channel_sku['custom_fields'][$key] as $k => $v) {
 							$data['product']['variants'][$j][$k] = $v;
 						}
 					}
 					else {
 						// top level
 						$data['product'][$key] = $value;
 					}
 				}
 			}

			$j++;
		}

    	return $data;
    }

    public function preparePrice($sku, $remove_gst) {
    	if ($remove_gst) {
			$sku['sale_price'] = number_format(($sku['sale_price'] * 100 / 106), 2);
			$sku['unit_price'] = number_format(($sku['unit_price'] * 100 / 106), 2);
		}

		$price = $sku['unit_price'];
    	if ($sku['sale_price'] > 0) {
			if(strtotime($sku['sale_start_date'])==false || strtotime($sku['sale_end_date'])==false) {
				$message = 'Sales period must be specified with Listing price / Invalid sales period format.';
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
				return false;
			} else {
				$now 				= Carbon::now($this->channel->timezone);
				$sales_start_date 	= Carbon::createFromFormat('Y-m-d',$sku['sale_start_date'],$this->channel->timezone);
				$sales_end_date 	= Carbon::createFromFormat('Y-m-d',$sku['sale_end_date'],$this->channel->timezone);

				if($sales_end_date->gte($sales_start_date) && $now->gte($sales_start_date) && $now->lte($sales_end_date))
					$price = $sku['sale_price'];
			}
		}

		$this->livePriceSkus[$sku['channel_sku_id']] = $price; 
		return $price;
    }

    private function prepareSku(array $sku, $isCreate = true) {
    	$price = $this->preparePrice($sku, $sku['remove_gst']);
        if ($price === false) return false;

		$data['variant'] = array(
			'sku'					=> $sku['hubwire_sku'],
			'inventory_quantity' 	=> ($sku['is_active']) ? $sku['quantity'] : 0,
			'grams'					=> $sku['weight'],
			'barcode'				=> $sku['hubwire_sku'],
			'requires_shipping' 	=> ($this->channel->isPos) ? false : true,
			'price'					=> $price,
			'compare_at_price' 		=> ($price < $sku['unit_price']) ? $sku['unit_price'] : 0,
		);

    	if ($isCreate) {
    		$data['variant']['title'] = '';
    		$data['variant']['inventory_policy'] = 'deny';
    		$data['variant']['inventory_management'] = 'shopify';

    		$i = 1;
			foreach($sku['options'] as $optionType => $optionValue){
				if($i > 3) break;
				$data['variant']["option$i"] = htmlspecialchars($optionValue);
				$i++;
			}
    	}

    	return $data;
    }

    public function getProductsQty(array $tp_prod_ids){
    	$shopify = $this->api();
    	$productIds = implode(',', $tp_prod_ids);
		$return = array();
		$filters = array();

		// set
		$filters['fields'] = 'variants';
		$filters['ids'] = $productIds;

		$response = $shopify('GET', '/admin/products.json', $filters);

		if($response === false){
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		foreach ($response as $product) {
			foreach($product['variants'] as $sku){
				$return[] = array(
					'chnl_sku_ref_id'	=>	$sku['id'],
					'product_ref_id'	=>	$sku['product_id'],
					'stock_qty'			=>	$sku['inventory_quantity']
				);
			}
		}

		return $return;
	}
}
