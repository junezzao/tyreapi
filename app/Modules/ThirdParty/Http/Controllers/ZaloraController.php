<?php
namespace App\Modules\ThirdParty\Http\Controllers;

use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Models\Admin\ReservedQuantity;
use Monolog;
use Carbon\Carbon;

/**
 * The ZaloraController class contains various method for us to
 * interact with Zalora API.
 *
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */

class ZaloraController extends SellerCenterController
{
	public $channel, $api_name, $__api, $customLog, $sync;
	private $error_data = array();
	private $livePriceSkus = array();

	public function __construct(){
		$this->api_name = 'Zalora';

		$this->customLog = new Monolog\Logger('Zalora Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/zalora.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error Zalora';
		$this->error_data['File'] = __FILE__;
	}

	public function prepareProduct(array $product, $isCreate = true)
    {
    	if (empty($product['category'])) {
			$message = 'Please assign category for this product.';
			$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			return array('success' => false, 'error' => $this->error_data);
		}

		// //product detail
		if(empty($product['description']) || $product['description'] == '')
		{
			$message = 'Please assign description for this product.';
			$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			return array('success' => false, 'error' => $this->error_data);
		}

    	$data = [];
    	$i = 0;
    	$parent = $product['parent'];
    	foreach($product['sku_in_channel'] as $channel_sku_id => $channel_sku)
    	{
    		$sku_weight_kg = $channel_sku['weight']*0.001;
			$productData = [
				'Brand' 			=> $product['brand'],
				'Description'		=> $product['description'],
				'Name'				=> $product['name'],
				'Price'				=> $channel_sku['unit_price'],
				'PrimaryCategory'	=> $product['category'],
				'SellerSku'			=> $channel_sku['hubwire_sku'],
				'TaxClass'			=> 'default',
				'Condition'			=> 'NEW',
				'ProductData'		=> [
					'Gender'=>'Female'
					],
				// 'Quantity'=>$channel_sku['quantity'],
				'Status'			=> $channel_sku['is_active']?'active':'inactive',
				'Variation'			=> isset($channel_sku['options']['Size']) ? $channel_sku['options']['Size'] : ""
			];

			$price = $this->preparePrice($channel_sku, true);
			$productData['Price'] 		  = $price['product']['Product'][0]['Price'];
			$productData['SalePrice'] 	  = $price['product']['Product'][0]['SalePrice'];
			$productData['SaleStartDate'] = $price['product']['Product'][0]['SaleStartDate'];
			$productData['SaleEndDate']   = $price['product']['Product'][0]['SaleEndDate'];

			if($isCreate)
			{
				$productData['ParentSku'] = $parent;
			}

			$temp_qty = $channel_sku['quantity'];

			// check if channel_sku has sold quantity
			$reserved_qty = ReservedQuantity::where('channel_sku_id','=',$channel_sku_id)
								->first();

			if(!empty($resevered_qty)) {
				$temp_qty += $reserved_qty->quantity;
			}

			//if (!empty($temp_qty)) {
				$productData['Quantity']= ($temp_qty < 0) ? 0 : $temp_qty;
			//}

			if(!empty($channel_sku['custom_fields'])) {
 				foreach ($channel_sku['custom_fields'] as $key => $value) {
 					if (strcasecmp($key, 'ProductData') == 0) {
 						// nested level e.g. ProductData-grams
 						foreach ($channel_sku['custom_fields'][$key] as $k => $v) {
 							if ($k == "ShortDescription")
	 						{
	 							$highlights = explode(PHP_EOL, $v);
	 							$shortDesc = "<ul>";

	 							foreach ($highlights as $highlight)
	 							{
	 								$shortDesc .= "<li>" . $highlight . "</li>";
	 							}

	 							$shortDesc .= "</ul>";
	 							$v = $shortDesc;
	 						}

 							$productData['ProductData'][$k] = $v;
 						}
 					}
 					else {
 						// top level
 						$productData[$key] = $value;
 					}
 				}
 			}

 			if($i == 0 && $isCreate)
			{
				$parent = $channel_sku['hubwire_sku'];
			}
			$i++;
 			$data['Product'][] = $productData;
		}
		
		return ['success' => true, 'product' => $data, 'livePriceSkus'=>$this->livePriceSkus];
    }

    public function preparePrice(array $product, $isSku = false) {
    	if($isSku) {
    		$product['sku_in_channel'][$product['channel_sku_id']] = $product;
    	}

    	foreach($product['sku_in_channel'] as $channel_sku_id => $channel_sku) {
	        $data_product = array(
	    			'SellerSku'		=> $channel_sku['hubwire_sku'],
					'Price'			=> $channel_sku['unit_price'],
					'SalePrice' 	=> '',
					'SaleStartDate' => '',
					'SaleEndDate' 	=> ''
	    		);

	    	$price = $channel_sku['unit_price'];
			if(!empty($channel_sku['sale_price']) && $channel_sku['sale_price'] > 0)
			{
				if(strtotime($channel_sku['sale_start_date'])==false || strtotime($channel_sku['sale_end_date'])==false)
				{
					$message = 'Sales period must be specified with Listing price / Invalid sales period format.';
					$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
					return array('success' => false, 'error' => $this->error_data);
				}

				$now 				= Carbon::now($this->channel->timezone);
				$sales_start_date 	= Carbon::createFromFormat('Y-m-d H:i:s',trim($channel_sku['sale_start_date']).' 00:00:00',$this->channel->timezone);
				$sales_end_date 	= Carbon::createFromFormat('Y-m-d H:i:s',trim($channel_sku['sale_end_date']).' 23:59:59',$this->channel->timezone);

				if($sales_end_date->gte($sales_start_date))
				{
					$data_product['SalePrice'] 		= $channel_sku['sale_price'];
					$data_product['SaleStartDate'] 	= $sales_start_date->format('c');
					$data_product['SaleEndDate'] 	= $sales_end_date->format('c');
				}

				if($sales_end_date->gte($sales_start_date) && $now->gte($sales_start_date) && $now->lte($sales_end_date))
					$price = $channel_sku['sale_price'];
			}
			
			$data['Product'][] = $data_product;
			$this->livePriceSkus[$channel_sku['channel_sku_id']] = $price; 
		}

		return ['success'=>true, 'product'=>$data, 'livePriceSkus'=>$this->livePriceSkus];
    }

 	public function getShippingProviderDetail($request) {
    	
    	$getShippingProviderDetail = array();
    	$params['Action'] = 'GetShipmentProviders';

		$sellerCenter = $this->api($this->channel);
		$response = $sellerCenter('GET', $params, array());
		
		if($response['success']==true){
			$details = $response['Body']['ShipmentProviders']['ShipmentProvider'];
			foreach ($details as $type => $datas) {
				if(is_array($datas)){
					if($datas['Cod']==0){
						$getShippingProviderDetail['name'] = $datas['Name'];
					}elseif ($datas['Cod']==1) {
						$getShippingProviderDetail['cod'] = $datas['Name'];
					}
				}else{
					if($type=="Name"){
						$getShippingProviderDetail['name'] = $datas;	
					}
				}
				
			}
			$getShippingProviderDetail['success'] = true;
			return $getShippingProviderDetail;
		}else{
			$getShippingProviderDetail['success'] = false;
			return $getShippingProviderDetail;
		}
		
	}
	
	//end
}
