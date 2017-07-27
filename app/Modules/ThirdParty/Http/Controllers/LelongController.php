<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Controllers\Admin\AdminController;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Repositories\LelongRepo;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Config;

use App\Models\Admin\Order;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ChannelSKU;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;

use Monolog;
use Log;
use Input;
use Response;
use Request;
use Carbon\Carbon;

class LelongController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $__api, $customLog, $sync, $order;
	private $error_data = array();
	private $livePriceSkus = array();

	public function __construct() {
		$this->api_name = get_class($this);

		$this->customLog = new Monolog\Logger('Lelong Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/lelong.log', Monolog\Logger::INFO));
	
		$this->error_data['subject'] = 'Error Lelong';
		$this->error_data['File'] = __FILE__;

		set_exception_handler(array($this, 'exceptionHandler'));
	}

	public function exceptionHandler($e) {
		$this->error_data['Line'] = $e->getLine();
		$this->error_data['ErrorDescription'] = $e->getMessage();
		
		Log::info('LelongController (' . $e->getLine() . ') >> Error: ' . $e->getMessage());
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
		$this->__setApi($channel);
		$this->sync = is_null($sync) ? null : $sync;
	}

	public function setOrder($order)
	{
		$this->order = $order;
	}

	public function api() {
		return $this->__api;
	}

	private function __setApi($channel_id) {
		$channel = $this->channel;
		$this->__api = function($method, $url , $params = array()) use ($channel) {

			$logInfo = "";
			$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
			$logInfo .= "Request Body/Query: ";
			$this->customLog->addInfo($logInfo, $params);

			$this->error_data['DataSent'] = $params;

			foreach ($params as $key => $value) {
				$params[$key] = json_encode($value);
			}

			$url = 'https://members.lelong.com.my' . $url; ///e.g: Auc/Member/Feed/feed.asp
			$params['UserID'] = $this->channel->channel_detail->api_password;
			$params['Key'] = $this->channel->channel_detail->api_key;

			$guzzle = new Client();
			$requestHeaders = array(
				'Content-Type' => 'application/x-www-form-urlencoded'
			);

			$request = new GuzzleRequest($method, $url, $requestHeaders);

			try {
				$response = $guzzle->send($request, array('form_params' => $params, 'http_errors' => false));
			}
			catch (RequestException $e){
				$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;

				$response = $e->getResponse();
				$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';

				$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

				if ($e->hasResponse()) {
					$this->customLog->addError(print_r($e->getResponse(), true));
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

			$response = str_replace('<hr>', '', $response->getBody()->getContents());
			$response = json_decode($response, true);
			
			$this->customLog->addInfo('Lelong.API Response >> ', $response);

			if($response['result'] < 0) {
				$message = $response['resultdescription'];
				$this->error_data['message'] = $message;
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
				return false;
			}

			return $response;
		};
	}

	/*
		Process Input from Shopify Webhooks and verify Webhook Signature
	*/
	public function receiver(){
		try{
			//verify userID/ Key
			$userID = explode("@",Input::get('UserID'))[0];
			$key = str_replace('{','',str_replace('}','',Input::get('Key')));
			
			//verify package source and get the corresponding channel ID
			return $this->verify_feed($userID, $key);
		}
		catch(Exception $e){
			$error = array();
			$error['error_desc'] = "RequestException : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	private function verify_feed($userID, $key)
	{
		if(empty($userID) ||empty($key)){
			// handle if values is empty
			return $return['verified'] = false;
		}

		//get channel by ID & Key
		$channel = ChannelDetails::where('api_password', $userID)->where('api_key', $key)->first()->channel;
		
		if (empty($channel)){
			$return['verified'] = false;
		}else{
			$this->channel = $channel;
			$return['verified'] = true;
		}

		return $return;
	}

	/*
	 	*** On Lelong Webhook Events  - Start
	 	@$data  relavant information retrieved from database
	*/
	public function order_create($data) {
		$this->customLog->addInfo('Lelong.order_create >> data: '. print_r($data, true));

		//check if have orderInformation
		$order = $data['OrderInformation'];
		if (empty($order)){
			$error = array();
			$error['error_desc'] = 'Lelong.order bad request >> empty order';
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}

		$orderInfo = json_decode($data['OrderInformation'],true);
        $lelongResp = array();
        $lelongResp['version'] = $orderInfo['version'];
        $lelongResp['orderid'] = $orderInfo['orderid'];
        $lelongResp['action'] = $orderInfo['action'];

        $check = Order::where('tp_order_id', $orderInfo['orderid'])->where('channel_id', $this->channel->id)->first();
        if(!empty($check)) {
        	$lelongResp['status'] = 'Success';
        	$lelongResp['merchantorderid'] = $check->id;
        	$lelongResp['success'] = true;
        	return $lelongResp;
        }

        $channel = Channel::where('id', $this->channel->id)->first();
        $response = LelongRepo::processOrder($orderInfo, $channel);
        if($response['success']) {
            $order_proc = new OrderProc($this->channel->id, new $this->channel->channel_type->controller);
            $response = $order_proc->createOrder($this->channel->id, $response);
        }

        if($response['success']) {
            $lelongResp['merchantorderid'] = $response['order_id']; // hubwire order_id
            $lelongResp['status'] = 'Success';
        } else {
            $lelongResp['status'] = 'Failed';
        }

        $lelongResp['desc'] = $lelongResp['action'].' '.$lelongResp['status'];
        $lelongResp = $lelongResp + $response;
        return $lelongResp;
	}

	/*
		Response Channel SKU quantity - Lelong ( Check Inventory)
		https://members.lelong.com.my/Auc/Member/Feed/doc-check-inventory-form.asp
	*/
	public function inventory_check($data){
		$channel_sku_id = "";
		try {
			$this->error_data['handler']['data'] = $data;
			$this->customLog->addInfo('Lelong.inventory >> input : ',Input::all());
			$this->customLog->addInfo('Lelong.inventory >> data : '.print_r($data, true));
			//decode Input
			$query = json_decode(Input::get("Query"), true);
			$product_id = $query['guid'];
			$channel_sku_id = $query['usersku'];
			$this->error_data['handler']['Query'] = $query;

			//get quantity
			$channel_sku = ChannelSKU::findOrFail($channel_sku_id);
			
			if($this->channel->id != $channel_sku->channel_id){
				Log::info('Lelong.inventory >> Fail to response as Channel ID for Channel SKU does not match, channel_sku_id : '. $channel_sku_id);
				$response['success'] = false;
				$response['error'] = 'Channel details does not tally for channel_sku_id.';
				return $response;
			}

			//output
			$response['success'] = true;
			$response['version'] = $query['version'];
			$response['action'] = $query['action'];
			$response['guid'] = $product_id;
			$response['usersku'] = $channel_sku_id;
			$response['quantity'] = $channel_sku->channel_sku_quantity;
			$this->error_data['handler']['response'] = $response;

			$this->customLog->addInfo('Lelong.inventory >> output : '.print_r($response, true));
			return $response;
		} catch (Exception $e) {
			Log::info('Lelong.inventory >> Fail to response channel_sku_id :'.$channel_sku_id.' Error :'.$e->getMessage());

			$response['success'] = false;
			$response['error'] =  'Unknown Error Encountered';
			$this->error_data['handler']['Function'] = __FUNCTION__;
			$this->error_data['handler']['Line'] = $e->getLine();
			$this->error_data['handler']['ErrorDescription'] = $e->getMessage().' in '.$e->getFile().' line '.$e->getLine();
			$this->ErrorAlert($this->error_data);
			return $response;
		}

	}

	public function createProduct(array $product, $bulk = false) {
		$this->updateProduct($product);
	}

    public function updateProduct(array $product, $bulk = false) {
    	try {
    		$lelong = $this->api();
			$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
			$storecategory = !empty($product['store_category']) ? $product['store_category'] : ((!empty($extra_info['store_category']) && $extra_info['store_category'] != 0) ? $extra_info['store_category'] : $product['default_category']);
			
			if (empty($storecategory)) {
				$message = 'Please set default store category in Channel > Lelong > Settings > API Settings | Channel: ' . $this->channel->id;
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			}

			if (empty($product['category'])) {
				$message = 'Please assign category for this product. | Channel: ' . $this->channel->id;
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			}

			if (empty($extra_info['shipping_cost'])) {
				$message = 'Please set default Shipping Cost in Channel > Lelong > Settings > Shipping Settings | Channel: ' . $this->channel->id;
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			}

			$cost_str = $extra_info['shipping_cost'];

			$j = 1;
			$options = array();
			foreach ($product['options'] as $key => $value) {
				$options[] = array("optionName" . $j => $key, "optionDetail" . $j => implode(", ", array_unique($value)));
				$j++;
			}

			$data = array();
			$firstSku = reset($product['sku_in_channel']);
			$data['Product'] = array(
				"version"			=> '1.0',
				"guid"				=> $product['id'],
				"title"				=> $product['brand'] . ' ' . $product['name'],
				"category"			=> $product['category'],
				"storecategory"		=> $storecategory,
				"brand"				=> $product['brand'],
				"shipwithin"		=> !empty($extra_info['ship_within']) ? $extra_info['ship_within'] : '4',
				"state"				=> !empty($extra_info['state']) ? $extra_info['state'] : 'Selangor',
				"description"		=> trim(preg_replace('/\s+/', ' ', $product['description'])),
				"publishdate"		=> "",
				"active"			=> 2,
				"weight"			=> 1,
				"shippingprice"		=> $cost_str,
				"whopay"			=> !empty($extra_info['who_pay']) ? $extra_info['who_pay'] : 'BP',
				"shiptolocation"	=> !empty($extra_info['ship_to']) ? $extra_info['ship_to'] : 'M',
				"shippingmethod"	=> !empty($extra_info['shipping_method']) ? $extra_info['shipping_method'] : 'N',
				"paymentmethod"		=> !empty($extra_info['payment_method']) ? $extra_info['payment_method'] : 'N',
				"optionsstatus"		=> 0,
				"quantity"			=> 0,
				"msrp"				=> $firstSku['unit_price'],
				"options"			=> $options,
				"optionsdetails"	=> array(),
			);

			foreach ($product['sku_in_channel'] as $sku) {
				$price = $this->preparePrice($sku);

				if ($price === false) {
		        	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
                }

				if(!isset($data['Product']['price'])) $data['Product']['price'] = $price;

				if($data['Product']['price'] > $price) { // set least sku price as product price
					$data['Product']['price'] = $price;
				}

				if($sku['unit_price'] > $data['Product']['msrp']) { // set highest sku price as product Manufacturer's suggested retail price
					$data['Product']['msrp'] = $sku['unit_price'];
				}

				$data['Product']['quantity'] += $sku['quantity'];
			}
			$data['Product']['quantity'] = ($data['Product']['quantity'] < 0) ? 0 : $data['Product']['quantity'];

			$sku_index = 0;
			
			foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
				$sku_detail = '';
				foreach ($sku['options'] as $optionType => $optionValue) {
					$sku_detail .= $optionValue . " | ";
				}

				$skuprice = $this->preparePrice($sku);
                if ($skuprice === false) {
			        return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		        }

				$data['Product']['optionsdetails'][$sku_index] = array(
												'sku'			=> trim($sku_detail),
												'usersku'		=> $channelSkuId,
												'price'			=> $skuprice - $data['Product']['price'], // price difference with product price
												'quantity'		=> ($sku['quantity'] < 0) ? 0 : $sku['quantity'],
												'warningqty'	=> 0,
												'status'		=> $sku['is_active']
											);

				if($sku['is_active']) {
					$data['Product']['active'] = 1;
				}

				if (!empty($sku['custom_fields'])) {
	 				foreach ($sku['custom_fields'] as $key => $value) {
	 					if (strcasecmp($key, 'optionsdetails') == 0) {
	 						// nested level e.g. optionsdetails-msrp
	 						foreach ($sku['custom_fields'][$key] as $k => $v) {
	 							$data['Product']['optionsdetails'][$sku_index][$k] = $v;
	 						}
	 					}
	 					else {
	 						// top level
	 						$data['Product'][$key] = $value;
	 					}
	 				}
	 			}

	 			$sku_index++;
			}

			if (!empty($product['default_media'])) {
				$data['Product']['image'] = str_replace("https", "http", $$product['default_media']['path']) . $$product['default_media']['ext'];
			}
			else {
				$data['Product']['image'] = str_replace("https", "http", $product['images'][0]['path']) . $product['images'][0]['ext'];
			}

			foreach ($product['images'] as $image) {
				$width = Config::get('marketplace.image_size.xl.width');
				$data['Product']['description'] .= '<p align="center"><img src="' . $image['path'] . $image['ext'] . '" width="' . $width . '" ></p>';

				$imageInfo = array(
					'media_id'			=> $image['id'],
					'ref_id'			=> $image['id'],
					'channel_id'		=> $this->channel->id,
					'third_party_name'	=> 'Lelong'
				);

				ProductSyncRepo::storeMediaThirdPartyInfo($imageInfo);
			}

			$response = $lelong('POST', '/Auc/Member/Feed/feed.asp', $data);

			if ($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$productThirdParty = array(
				'ref_id' => $response['guid'],
				'channel_id' => $this->channel->id,
				'third_party_name' => 'Lelong',
				'product_id' => $this->sync->ref_table_id
			);
			
			ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

			foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
				$skuInfo = array(
					'hubwire_sku'	=> $sku['hubwire_sku'],
					'merchant_id'	=> $product['merchant_id'],
					'channel_id'	=> $this->channel->id,
					'ref_id'		=> $channelSkuId,
					'product_id'	=> $product['id']
				);

				$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($skuInfo);

				if (!$storeResponse) {
					$message = 'Expected Response Not Match With Hubwire Model: SKU ' . $sku['hubwire_sku'] . ' | data sent: ' . json_encode($data);
					$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				}
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
		}
		catch (Exception $e) {
			$message = $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}
    }

    public function updatePrice(array $product, $bulk = false) {
    	try {
    		$lelong = $this->api();
			$data = array();
			$firstSku = reset($product['sku_in_channel']);
			$data['Req'] = array(
				"version"			=> '1.0',
				"action"			=> "updateprice",
				"guid"				=> $product['id'],
				"msrp"				=> $firstSku['unit_price'],
				"optionsdetails"	=> array(),
			);

			foreach ($product['sku_in_channel'] as $sku) {
				$price = $this->preparePrice($sku);

				if ($price === false) {
		        	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
                }

				if(!isset($data['Req']['price'])) $data['Req']['price'] = $price;

				if($data['Req']['price'] > $price) { // set least sku price as product price
					$data['Req']['price'] = $price;
				}

				if($sku['unit_price'] > $data['Req']['msrp']) { // set highest sku price as product Manufacturer's suggested retail price
					$data['Req']['msrp'] = $sku['unit_price'];
				}
			}
			
			$sku_index = 0;
			foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
				$sku_detail = '';
				foreach ($sku['options'] as $optionType => $optionValue) {
					$sku_detail .= $optionValue . " | ";
				}

				$skuprice = $this->preparePrice($sku);
                if ($skuprice === false) {
			        return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		        }

				$data['Req']['optionsdetails'][$sku_index] = array(
												'sku'			=> trim($sku_detail),
												'usersku'		=> $channelSkuId,
												'price'			=> $skuprice - $data['Req']['price'], // price difference with product price
											);

	 			$sku_index++;
			}

			$response = $lelong('POST', '/Auc/Member/Feed/feed-server.asp', $data);
			if ($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
		}
		catch (Exception $e) {
			$message = $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}
    }

	public function updateVisibility(array $product, $bulk = false) { /* not implemented */ }

    public function deleteProduct(array $product, $bulk = false) { /* not implemented */ }

    public function createSku(array $sku, $bulk = false) {
		$this->updateProduct($sku);
	}

    public function updateSku(array $sku, $bulk = false) {
		$this->updateProduct($sku);
	}

    public function updateQuantity(array $product, $bulk = false) {
    	try {
    		$lelong = $this->api();
			$data = array();
			$data['Req'] = array(
				"version"			=> '1.0',
				"action"			=> "updatequantity",
				"guid"				=> $product['id'],
				"quantity"			=> 0,
				"optionsdetails"	=> array(),
			);

			$sku_index = 0;
			foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
				$sku['quantity'] = ($sku['quantity'] < 0) ? 0 : $sku['quantity'];
				$data['Req']['quantity'] += $sku['quantity'];
				$sku_detail = '';
				foreach ($sku['options'] as $optionType => $optionValue) {
					$sku_detail .= $optionValue . " | ";
				}

				$data['Req']['optionsdetails'][$sku_index] = array(
												'sku'			=> trim($sku_detail),
												'usersku'		=> $channelSkuId,
												'quantity'		=> ($sku['quantity'] < 0) ? 0 : $sku['quantity'],
											);
	 			$sku_index++;
			}
			$response = $lelong('POST', '/Auc/Member/Feed/feed-server.asp', $data);

			if ($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();
		}
		catch (Exception $e) {
			$message = $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}

    }

    public function deleteSku(array $sku, $bulk = false) { /* not implemented */ }

    public function updateImages(array $data) {
		$this->updateProduct($data);
	}

	public function readyToShip($input)
	{
		$data = array();
		$this->error_data['handler']['orderid'] = $this->order->id;
		$this->error_data['handler']['channel_id'] = $this->channel->id;

		try
		{
			$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
			$lelong = $this->api();
			$data['Req']['version'] = Config::get('lelong.default.version');
			$data['Req']['action'] = "updateshipmenttracking";
			$data['Req']['orderid'] = $this->order->tp_order_id;
			$data['Req']['shippingdaydd'] = date('d');
			$data['Req']['shippingdaymm'] = date('m');
			$data['Req']['shippingdayyyyy'] = date('Y');
			$data['Req']['courier'] = $extra_info['shipping_provider'];
			$data['Req']['trackingnumber'] = $input['tracking_no'];
			$data['Req']['combineshippingorderids'] = "";

			$this->error_data['handler']['data'] = $data;
			
			$this->customLog->addInfo('ReadyToShip sending Guzzle request... '. print_r($data, true));
			$response = $lelong('POST', '/Auc/Member/Feed/feed-server.asp', $data);
			$this->customLog->addInfo('ReadyToShip response... '. print_r($response, true));
			$this->error_data['handler']['response'] = $response;

			if($response === false)
			{
				return array('success'=>false, 'message'=>$this->error_data['message']);
			}
		}
		catch (Exception $e)
		{
			$message = $e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}

		return array('success'=>true, 'tracking_no'=>$input['tracking_no']);
	}

	public function importStoreCategories() {
		try {
			$data['Req']['version'] = '1.0';
			$data['Req']['action'] = "liststorecategory";

			$lelong = $this->api();
			$response = $lelong('POST', '/Auc/Member/Feed/feed-server.asp', $data);

			if ($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			return Response::JSON(array('success' => true, 'response' => json_encode($response)), MpUtils::getStatusCode('OK_STATUS'));
		}
		catch (Exception $e) {
			$message = $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
		}
	}

    public function getOrders(array $filters) {}

    public function getSingleOrder($order_code) {}

    public function setSalesStatus($order_id, array $details) {}

    public function sendResponse($response) {}

    public function preparePrice($sku) {
    	if($sku['sale_price'] > 0)
		{
			if(strtotime($sku['sale_start_date'])==false || strtotime($sku['sale_end_date'])==false)
			{
				$message = 'Sales period must be specified with Listing price / Invalid sales period format.';
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
				return false;
			}
			else 
			{
				$now 				= Carbon::now($this->channel->timezone);
				$sales_start_date 	= Carbon::createFromFormat('Y-m-d',$sku['sale_start_date'],$this->channel->timezone);
				$sales_end_date 	= Carbon::createFromFormat('Y-m-d',$sku['sale_end_date'],$this->channel->timezone);
				
				if($sales_end_date->gte($sales_start_date) && $now->gte($sales_start_date) && $now->lte($sales_end_date))
					$price = $sku['sale_price'];
				else
					$price = $sku['unit_price'];
			}
		}
		else
		{
			$price = $sku['unit_price'];
		}

		$this->livePriceSkus[$sku['channel_sku_id']] = $price; 
		return $price;
    }
}
