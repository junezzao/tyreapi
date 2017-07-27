<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;

use App\Http\Controllers\Admin\AdminController;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;

use App\Models\Admin\Channel;
use App\Models\Admin\ThirdPartySync;

class ProductProcessingService extends AdminController
{
    private $channel, $thirdParty, $sync;
    private $productSyncRepo;
	private $error = array();

	public function __construct($channel_id, MarketplaceInterface $thirdParty, ThirdPartySync $sync = null, $isBulk = false)
	{
		try {
			$channel = Channel::with('channel_detail', 'channel_type')->findOrFail($channel_id);

	        $this->channel = $channel;
	        $this->thirdParty = $thirdParty;

	        if (!is_null($sync)) {
	        	$this->sync = $sync;
            	$this->thirdParty->initialize($this->channel, $this->sync);
	            $this->productSyncRepo = new ProductSyncRepo($this->channel, $this->sync);
	        }elseif($isBulk){
	        	$this->thirdParty->initialize($this->channel, null);
	        	$this->productSyncRepo = new ProductSyncRepo($this->channel, null);
	        }else{
	        	$this->thirdParty->initialize($this->channel, $this->sync);
	        }
		}
		catch (Exception $e) {
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public function processSync() {
		$this->sync->sent_time = date('Y-m-d H:i:s');
		$this->updateSyncStatus('PROCESSING');

		$channelType = $this->channel->channel_type->name;

		if(strcasecmp($this->sync->action, 'createProduct') != 0 && !$this->productSyncRepo->checkIsProductInMarketplace()) {
			$this->updateSyncStatus('FAILED', 'ARC Error: Product does not exist in marketplace.');
			return;
		}
		else if (strcasecmp($this->sync->action, 'createProduct') == 0 && $this->productSyncRepo->checkIsProductInMarketplace()) {
			$this->updateSyncStatus('FAILED', 'ARC Error: Product already exist in marketplace.');
			return;
		}

		if (strcasecmp($this->sync->action, 'updateProduct') == 0) {
			if (!$this->productSyncRepo->checkIsSkusInMarketplace()) {
				return;
			}
		}

		// All product prices in Hubwire system must be GST inclusive
		// $gstInclusive determine whether to sync prices with / without GST to marketplace, e.g. Midvalley Shopify POS is not GST inclusive
		$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
		$removeGst = (isset($extra_info['remove_gst']) && $extra_info['remove_gst'] == 'True') ? true : false;
		$event = $this->sync->action;
		$event = ($event == 'createSKU') ? 'createSku' : (($event == 'updateSKU') ? 'updateSku' : (($event == 'deleteSKU') ? 'deleteSku' : $event));
		switch($event) {
			case 'createSku':
			case 'updateSku':
			case 'deleteSku':
			case 'updateQuantity':
			case 'updatePrice':
				$marketplaceRequiresProductDetails = in_array($event, array('updateQuantity')) ? ['Lelong', 'Zalora', 'Lazada'] : ['11Street', 'Lelong', 'Zalora', 'Lazada', 'LazadaSC'];
				$marketplaceRequiresOnlySingleSku = in_array($event, array('updateQuantity')) ? ['Zalora', '11Street', 'Lazada', 'LazadaSC'] : ['Zalora', 'Lazada', 'LazadaSC'];

				if($event == 'updatePrice') {
					$marketplaceRequiresProductDetails = ['11Street', 'Lelong', 'Zalora', 'Lazada'];
					$marketplaceRequiresOnlySingleSku = ['Zalora', 'Lazada', 'LazadaSC'];
				}

				$withProductDetails = in_array($channelType, $marketplaceRequiresProductDetails) ? true : false;
				$singleSku = in_array($channelType, $marketplaceRequiresOnlySingleSku) ? true : false;

				$data = $this->productSyncRepo->getChannelSkuDetails($this->sync->ref_table_id, $withProductDetails, $singleSku);
				if (strcasecmp($this->productSyncRepo->syncStatus, 'FAILED') == 0) {
					return;
				}
				$data['remove_gst'] = $removeGst;
				$this->thirdParty->{$event}($data, false);
				break;
			case 'createProduct':
			case 'updateProduct':
			case 'deleteProduct':
			case 'updateVisibility':
				$data = $this->productSyncRepo->getProductDetails();

				if (strcasecmp($this->productSyncRepo->syncStatus, 'FAILED') == 0) {
					return;
				}

				$data['remove_gst'] = $removeGst;
				$this->thirdParty->{$event}($data, false);
				break;
			case 'uploadNewMedia':
			case 'setDefaultMedia':
			case 'deleteMedia':
			case 'updateMedia':
				$marketplaceRequiresProductDetails = ['11Street', 'Lelong', 'Zalora', 'Lazada', 'LazadaSC'];
				$withProductDetails = in_array($channelType, $marketplaceRequiresProductDetails) ? true : false;

				$data = $this->productSyncRepo->getProductImages($withProductDetails);
				if (strcasecmp($this->productSyncRepo->syncStatus, 'FAILED') == 0) {
					return;
				}
				$this->thirdParty->updateImages($data);
				break;
			default:
				$this->updateSyncStatus('FAILED', 'Action not found.');
				break;
		}
	}

	public function processBulkSync($bulkSync){
		$tempSyncs = array();
        foreach($bulkSync['syncData'] as $data){
            // check to prevent updating same sync over and over again
            if(!in_array($data['sync']->id, $tempSyncs)){
                $data['sync']->sent_time = date('Y-m-d H:i:s');
                $data['sync']->status = 'PROCESSING';
                $data['sync']->save();
                // push sync id to array to indicate this sync has already been updated to fail
                $tempSyncs[] = $data['sync']->id;
            }
        }

        $failedSyncs = array();

        $channelType = $this->channel->channel_type->name;

        $marketplaceRequiresProductDetails = in_array($bulkSync['action'], array('updateQuantity', 'updatePrice')) ? ['Lelong', 'Zalora', 'Lazada', 'LazadaSC'] : ['11Street', 'Lelong', 'Zalora', 'Lazada', 'LazadaSC'];
		$marketplaceRequiresOnlySingleSku = in_array($bulkSync['action'], array('updateQuantity', 'updatePrice')) ? ['Zalora', '11Street', 'Lazada'] : ['Zalora', 'Lazada', 'LazadaSC'];

		$withProductDetails = in_array($channelType, $marketplaceRequiresProductDetails) ? true : false;
		$singleSku = in_array($channelType, $marketplaceRequiresOnlySingleSku) ? true : false;

        $extra_info = json_decode($this->channel->channel_detail->extra_info, true);
		$removeGst = (isset($extra_info['remove_gst']) && $extra_info['remove_gst'] == 'True') ? true : false;

        // start looping through bulk sync here
        foreach($bulkSync['syncData'] as $index => $data){
  			// check if current sync has already been marked as failed
        	if(!in_array($data['sync']->id, $failedSyncs)){
        		$this->productSyncRepo->setSync($data['sync']);
	        	if(strcasecmp($data['sync']->action, 'createProduct') != 0 && !$this->productSyncRepo->checkIsProductInMarketplace()) {
	        		$this->sync = $data['sync'];
	        		$this->updateSyncStatus('FAILED', 'ARC Error: Product does not exist in marketplace.');
	        		$failedSyncs[] = $data['sync']->id;
	        		unset($bulkSync['syncData'][$index]);
	        		continue;
				}
				else if (strcasecmp($data['sync']->action, 'createProduct') == 0 && $this->productSyncRepo->checkIsProductInMarketplace()) {
					$this->sync = $data['sync'];
					$this->updateSyncStatus('FAILED', 'ARC Error: Product already exist in marketplace.');
					$failedSyncs[] = $data['sync']->id;
					unset($bulkSync['syncData'][$index]);
					continue;
				}

				$bulkSync['syncData'][$index]['remove_gst'] = $removeGst;

				switch($bulkSync['action']){
					case 'bulkCreate':
					case 'bulkUpdate':
					case 'bulkQty':
					case 'bulkPrice':
						$bulkSync['syncData'][$index]['productData'] = $this->productSyncRepo->getChannelSkuDetails($data['chnlSkuId'], $withProductDetails, $singleSku);
						break;
					case 'bulkMedia':
						// $marketplaceRequiresProductDetails = ['11Street', 'Lelong', 'Zalora', 'Lazada', 'LazadaSC'];
						$withProductDetails = in_array($channelType, $marketplaceRequiresProductDetails) ? true : false;
						$bulkSync['syncData'][$index]['productData'] = $this->productSyncRepo->getProductImages($withProductDetails);
						break;
					default:
						// handle error for action not found
						break;
				}

				if (strcasecmp($this->productSyncRepo->syncStatus, 'FAILED') == 0) {
					$failedSyncs[] = $data['sync']->id;
					unset($bulkSync['syncData'][$index]);
				}

        	}else{
        		// if sync has already been marked as failed, unset from array
        		unset($bulkSync['syncData'][$index]);
        	}
        }
        if(count($bulkSync['syncData']) > 0){
        	switch($bulkSync['action']){
				case 'bulkCreate':
				case 'bulkUpdate':
				case 'bulkMedia':
				case 'bulkQty':
				case 'bulkPrice':
					$this->thirdParty->{$bulkSync['action']}($bulkSync);
					break;
				default:
					// handle error for action not found
					break;
			}
        }
	}

	private function updateSyncStatus($status, $remarks = null) {
		if (!is_null($remarks)) {
			$this->sync->remarks = $remarks;
		}

		$this->sync->status = strtoupper($status);
		$this->sync->save();
	}

	public function getProductQty($tp_product_id)
	{
		$output = $this->thirdParty->getProductQty($tp_product_id);

        return $output;
	}

	public function getProductsQty($tp_product_id)
	{
		$output = $this->thirdParty->getProductsQty($tp_product_id, $this->channel);

        return $output;
	}
}
