<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Controllers\Admin\AdminController;
use GuzzleHttp\Exception\RequestException as RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

use App\Repositories\Eloquent\SyncRepository;
use App\Modules\ThirdParty\Repositories\ShopeeRepo;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;

use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Config;

use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Document;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ProductMedia;

use Monolog;
use DateTime;
use Carbon\Carbon;
use SimpleXMLElement;
use Exception;
use Response;
use DateTimezone;
use Image;
use App\Services\MediaService as MediaService;

class ShopeeController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $__api, $customLog, $sync, $order;
	private $error_data = array();

	public function __construct(){
		$this->api_name = 'Shopee';

		$this->customLog = new Monolog\Logger('Shopee Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/shopee.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error Shopee';
		$this->error_data['File'] = __FILE__;
	}

	public function exceptionHandler($e) {
		$error['subject'] = 'Error '. get_class($this);
		$error['File'] = __FILE__;
		$error['Line'] = $e->getLine();
		$error['ErrorDescription'] = $e->getMessage();
		$this->error_data = $error + $this->error_data;

		Log::info(__CLASS__. '(' . $e->getLine() . ') >> Error: ' . $e->getMessage());
	}

	public function errorHandler($function, $line, $response, $message = '') {
		if(!empty($response)) {
			$this->error_data['response'] = $response;
		}

		$error['subject'] = 'Error '. get_class($this);
		$error['File'] = __FILE__;
		$error['Function'] = $function;
		$error['Line'] = $line;
		$error['ErrorDescription'] = $message;
		$this->error_data = $error + $this->error_data;
		if(!empty($this->sync)) {
			$this->sync->status = 'FAILED';
			$this->sync->remarks = $message;
			$this->sync->save();
			$this->error_data['Sync'] = $this->sync->toArray();
		}

		$this->customLog->addError(json_encode($this->error_data));
		$this->ErrorAlert($this->error_data);
	}

	public function initialize($channel, ThirdPartySync $sync = null)
	{
		$this->__setApi($channel);
		$this->sync = is_null($sync) ? null : $sync;
		return $this;
	}


	public function api($channel = null) {
		if(!empty($channel)){
			$this->__setApi($channel);
		}
		return $this->__api;
	}

	private function __setApi($channel) {
		$this->channel = $channel;
		$this->__api = function($method, $endpoint , $params = array(), $sync_id = null) use ($channel)
		{
			try{
				$url = '';

				$extra_info = json_decode($channel->channel_detail->extra_info);
				$params['shopid'] 			= intval($extra_info->shop_id);
				$params['partner_id'] 	  	= intval($channel->channel_detail->api_password);

				// The current time. Needed to create the Timestamp parameter below.
				$params['timestamp']		= Carbon::now('Asia/Kuala_Lumpur')->timestamp;
				$url 		=  $channel->channel_detail->api_secret.'/'. $endpoint;
				$secret_key = $channel->channel_detail->api_key;
				$auth_msg = $url.'|'.json_encode($params);

				// Compute signature and add it to the parameters.
				$signature = hash_hmac('SHA256', $auth_msg, $secret_key);
				$this->error_data['url'] = $url;

				// Log request
				$logInfo = "";
				$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
				$logInfo .= "URL: " . $url . " | ";
				$logInfo .= "Request Body/Query: " . json_encode($params);
				$this->customLog->addInfo($logInfo);

				$guzzle = new Client();
				$requestHeaders["Content-Type"] = "application/json";
				$requestHeaders["Authorization"] = $signature;
				$request = new GuzzleRequest($method, $url, $requestHeaders);

				$response = $guzzle->send($request, array('body' => json_encode($params), 'http_errors' => false));

			}
			catch (RequestException $e) {
				$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;

				$response = $e->getResponse();
				$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';

				$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

				if ($e->hasResponse()) {
					$message  = json_encode($e->getResponse(), true);
					$this->customLog->addError( !empty($message['error']) ? $message['error'] : $message );
			    }

				return ['success' => false];
			}
			catch(Exception $e) {
				$message = "Error send request. Unknown error. Request URL: " . $url;
				$message .= ' Error: ' . $e->getMessage() . ' at line ' . $e->getLine();

				$this->customLog->addError($message);

			    $this->errorHandler(__FUNCTION__, $e->getLine(), (!empty($response) ? $response : null), $message);
				return ['success' => false];
			}

			$response = json_decode($response->getBody()->getContents(), true);
			$response['success'] = true;

			if(!empty($response['error'])) {
				$response['success'] = false;
				$message = "Marketplace Error: " . $response['error'];
				$message .= " | Request URL: " . $url;
				$this->error_data['response'] = $response;
				$this->error_data['message'] = $response['error'];
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);

			}
			else {
				return $response;
			}
		};
	}

	/*
	 *
	 * Orders functions
	 *
	 */
	public function setOrder($order)
	{
		$this->order = $order;
	}

	public function getSingleOrder($order_code) {
		$tmp = [['ordersn'=>$order_code]];
		$response = $this->getMultipleOrderItems($tmp, $this->channel);
		$responses = ShopeeRepo::processOrder($tmp, $this->channel);
		$responses = array_values($responses);

		return $responses[0];
	}

	public function getOrders(array $filter) {
		$params = array();
		$endpoint = 'orders/basics';

		$response = ShopeeRepo::prepareOrderRequest(MpUtils::getTypeCode('GET_ORDER'), $filter);
		if(!$response['success'])
		{
			return $response;
		}

		$date = new DateTime($response['param']['startTime'], new DateTimezone('Asia/Kuala_Lumpur'));
		$date->setTimezone(new DateTimezone('UTC'));
		$dateEnd = new DateTime($response['param']['endTime'], new DateTimezone('Asia/Kuala_Lumpur'));
		$dateEnd->setTimezone(new DateTimezone('UTC'));

		$params['create_time_from'] 			= $date->getTimestamp();
		$params['create_time_to'] 				= $dateEnd->getTimestamp();
		$params['pagination_entries_per_page'] 	= 50;
		$params['pagination_offset']			= 0;

		$orders 	= array();
		$more 		= true;
		$api 		= $this->api($this->channel);

		while($more)
		{
			$response = $api('POST', $endpoint , $params );
			$more = $response['more'];

			if(isset($response['success']) && !$response['success']) {
				return $response;
			}

			$tmp = $response['orders'];

			if(empty($tmp))
			{
				$error = array();
				$error['error_desc'] = "Order is empty.";
				$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
				return MpUtils::errorResponse($error, __METHOD__, __LINE__);
			}

			$response2 = $this->getMultipleOrderItems($tmp, $this->channel);
			if(!$response2['success']) {
				return $response2; //failed to get Multiple order items.
			}
			$orders = array_merge($orders, $tmp);
			$params['pagination_offset'] += ($params['pagination_entries_per_page']);
		}

		return ShopeeRepo::processOrder($orders, $this->channel);
	}

	public function getOrderItems($channel, $tp_order_id) {

	}

	public function getMultipleOrderItems(&$orders, $channel) {
		/**
		** "orders": [
		**    {
		**      "ordersn": "17021512053517P",
		**      "order_status": "CANCELLED",
		**      "update_time": 1489979997
		**    }]
		**
		**/
		$order_sn = array();
		foreach($orders as $order)
		{
			$order_sn[] = $order['ordersn'];
		}

		$params = array();
		$endpoint = 'orders/detail';

		$params['ordersn_list'] = $order_sn;

		$api = $this->api($this->channel);
		$response = $api('POST', $endpoint , $params );

		$orders = $response['orders'];

		return ['success' => true];

	}

	public function getShippingProviderName() {

	}

	public function getConsignmentNumber($channel, $tp_order_id) {

	}

	public function readyToShip($input) {
		try
		{
			if(strlen($input['tracking_no']) > 0 && strlen($input['tracking_no']) < 6)
			{
				throw new Exception("Consignment No. must at least in 6 character.");
			}

			$order = $this->getSingleOrder($this->order->tp_order_id)['order'];
			$orderExtra = json_decode($order->tp_extra, true);

			if (strcasecmp($orderExtra['order_status'], 'cancelled') == 0) {
				throw new Exception("Order has been cancelled on Shopee.");
			}

			$params = array();
			$endpoint = 'logistics/tracking_number/set_mass';
			$params['info_list'][] = array('ordersn' => $this->order->tp_order_id, 'tracking_number' => $input['tracking_no'] );

			$this->error_data['handler']['params'] = $params;

			$api = $this->api($this->channel);

			$response = $api('POST', $endpoint , $params );
			$this->error_data['handler']['response'] = $response;

			$response['success'] = $response['result']['success_count'] > 0 ? true : false;

			return $response;
		}
		catch(Exception $e)
		{
			$response = array();
			$this->error_data['message'] = $message = 'Error: '. $e->getMessage();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
			return array('success'=>false, 'message' => (!empty($this->error_data['message'])) ? $this->error_data['message'] : $this->error_data['ErrorDescription']);
		}
	}

	// END ORDERS

	/*
	 *
	 * Product functions
	 *
	 */
	public function createProduct(array $product, $bulk = false){

	}

	public function updateProduct(array $product, $bulk = false){

	}

	public function updateVisibility(array $product, $bulk = false){

	}

	public function deleteProduct(array $product, $bulk = false){

	}

	public function createSku(array $sku, $bulk = false){

	}

	public function updateSku(array $sku, $bulk = false){

	}

	public function updateQuantity(array $sku, $bulk = false){

	}

	public function deleteSku(array $sku, $bulk = false){

	}

	public function updateImages(array $data){

	}

	// END PRODUCT

	public function sendResponse($response){

	}
}