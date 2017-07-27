<?php

namespace App\Modules\ThirdParty\Repositories;

use App\Repositories\CustomFieldsRepository;
use App\Repositories\Eloquent\SyncRepository;

use App\Models\Admin\Channel;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\Product;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\ProductThirdPartyCategory;
use App\Models\Admin\ProductMediaThirdParty;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\ThirdPartyCategory;
use App\Models\Admin\ThirdPartySync;

class ProductSyncRepo
{
	public $syncStatus;
	private $channel, $sync, $productId;
	private $productThirdParty, $ptpExtra, $thirdPartyCategory;

	public function __construct(Channel $channel, ThirdPartySync $sync = null)
	{
		$this->channel = $channel;
		if(!is_null($sync)){
			$this->setSync($sync);
		}
	}

	public function setSync(ThirdPartySync $sync){
		$this->sync = $sync;
		$this->syncStatus = $sync->status;
		$this->productId = $this->getProductId($sync);

		$this->productThirdParty = self::getProductThirdParty($this->productId, $this->channel->id);
		//$this->ptpExtra = !is_null($this->productThirdParty) ? unserialize($this->productThirdParty->extra) : null;
		$this->ptpExtra = !is_null($this->productThirdParty) ? (!is_null(json_decode($this->productThirdParty->extra, true)) ? json_decode($this->productThirdParty->extra, true) : unserialize($this->productThirdParty->extra)) : null;
		$this->thirdPartyCategory = ProductThirdPartyCategory::where('product_id', '=', $this->productId)
															->where('channel_id', '=', $this->channel->id)
															->first();
	}

	/*
	 *
	 * Gets data for sync
	 *
	 */
	public function getProductDetails() {
		$channelId = $this->channel->id;
		$product = Product::with('tags', 'brand', 'media', 'default_media', 'media_trashed')
				->with(['sku_in_channel' => function($query) use ($channelId) {
			                    $query->where('channel_sku.channel_id', '=', $channelId);
			                }])->findOrFail($this->productId);

		$tags = array();
		foreach($product->tags as $tag){
			$tags[] = $tag->value;
		}

		$defaultCategory = ThirdPartyCategory::where('channel_id', '=', $this->channel->id)->first();

		$options = array();
		$skuInChannel = $this->processSkus($product->sku_in_channel, true, $options);

		$defaultMedia = $product->getRelation('default_media');
		if (!is_null($defaultMedia)) {
			$path = $defaultMedia->media->media_url;
			if(strrpos($path, $defaultMedia->media->ext) != false && strrpos($path, $defaultMedia->media->ext) >= 0) {
				$path = substr($path, 0, (strlen($path) - strlen($defaultMedia->media->ext)));
			}

			$defaultImage = array(
				'id'		=> $defaultMedia->id,
				'path'		=> $path,
				'ext'		=> $defaultMedia->media->ext
			);
		}

		$productData = array(
			'id'				=> $product->id,
			'merchant_id'		=> $product->merchant_id,
			'name'				=> $product->name,
			'description'		=> $product->description,
			'short_description'	=> $product->description2,
			'brand'				=> !empty($product->getRelation('brand')) ? $product->getRelation('brand')->name : $product->brand,
			'category'			=> !empty($this->thirdPartyCategory) ? $this->thirdPartyCategory->cat_id : '',
			'default_category'	=> !empty($defaultCategory) ? $defaultCategory->ref_id : '',
			'tags'				=> (count($tags) > 0) ? implode(',', array_unique($tags)) : '',
			'options'			=> $options,
			'is_active'			=> $product->active ? true : false,
			'default_image'		=> !empty($defaultImage) ? $defaultImage : null,
			'images'			=> $this->processImages($product->media),
			'deleted_images'	=> $this->processImages($product->media_trashed, true),
			'sku_in_channel'	=> $skuInChannel,
			'product_ref'		=> !is_null($this->productThirdParty) ? $this->productThirdParty->ref_id : '',
			'parent'			=> !empty($this->ptpExtra['parentSku']) ? $this->ptpExtra['parentSku'] : null
		);

		if (strcasecmp($this->channel->channel_type->name, 'Lelong') == 0) {
			$productData['store_category'] = $this->getLelongStoreCategory($tags);
		}

		return $productData;
	}

	public function getChannelSkuDetails($channelSkuId, $withProduct = false, $single = false) {
		$channelSku = ChannelSKU::with('sku', 'sku_options')->findOrFail($channelSkuId);

		if ($withProduct) {
			$data = $this->getProductDetails();

			if ($single) {
				$tmp = $data['sku_in_channel'][$channelSkuId];
				unset($data['sku_in_channel']);
				$data['sku_in_channel'] = [];
				$data['sku_in_channel'][$channelSkuId] = $tmp;
			}
		}
		else {
			$sku = $this->processSkus(array($channelSku), false);

			$data = $sku[$channelSku->channel_sku_id];

			$data['merchant_id'] = $channelSku->sku->merchant_id;
			$data['product_id'] = $channelSku->product_id;
			$data['product_ref'] = !is_null($this->productThirdParty) ? $this->productThirdParty->ref_id : '';
			$data['parent'] = !empty($this->ptpExtra['parentSku']) ? $this->ptpExtra['parentSku'] : null;
		}

		return $data;
	}

	public function getProductImages($withProduct = false) {
		if ($withProduct) {
			$data = $this->getProductDetails();
		}
		else {
			$product = Product::with('media', 'media_trashed')->findOrFail($this->productId);
			$data['images'] = $this->processImages($product->media);
			$data['deleted_images'] = $this->processImages($product->media_trashed, true);
		}

		if (count($data['images']) == 0) {
			$this->updateSyncStatus('FAILED', 'Media not found.');
			return;
		}

		$data['parent'] = !empty($this->ptpExtra['parentSku']) ? $this->ptpExtra['parentSku'] : null;
		$data['product_ref'] = !is_null($this->productThirdParty) ? $this->productThirdParty->ref_id : '';

		return $data;
	}

	private function getCustomFieldsData($channelSkuId, $cfESRepo, $customFields) {
		$categoryLevelSeparator = (strcasecmp($this->channel->channel_type->name, 'Lelong') == 0) ? ':' : '/';
		$productCategoryId = !empty($this->thirdPartyCategory) ? $this->thirdPartyCategory->cat_id : '';
		$categoryLevels = array();

		if (!empty($productCategoryId)) {
			$categories = array_flip(config('categories.' . $this->channel->channel_type->name . '-' . $this->channel->channel_type->site));

			if (!isset($categories[$productCategoryId])) {
				$this->updateSyncStatus('FAILED', 'The category ID (' . $productCategoryId . ') for channel SKU ' . $channelSkuId . ' was not found. Please refer to category list.');
				return false;
			}
			else {
				$category = $categories[$productCategoryId];
			}

			$categoryLevels = explode($categoryLevelSeparator, $category);
 		}

		$data = array();

		$response = $cfESRepo->getCFData($channelSkuId);

		if (!is_array($response)) {
			$response = json_decode(json_encode($response), true);
		}

		$fieldData = array();
		if(!empty($response)){
			foreach ($response as $cfData) {
				$fieldData[$cfData['custom_field_id']] = $cfData['field_value'];
			}
		}

		foreach($customFields as $field) {
			$cfCategoryLevelCount = count(explode($categoryLevelSeparator, $field['category']));

			$parentCategory = '';
			if (count($categoryLevels) >= $cfCategoryLevelCount) {
				$parentCategory = implode($categoryLevelSeparator, array_slice($categoryLevels, 0, $cfCategoryLevelCount));
			}

			if (strcasecmp($field['category'], 'All') == 0 || (!empty($parentCategory) && strcasecmp($field['category'], $parentCategory) == 0)) {
				$parts = explode("-", $field['field_name'], 2);

				$value = (!empty($fieldData[$field['id']])) ? $fieldData[$field['id']] : $field['default_value'];

				if (!empty($value)) {
					if (count($parts) > 1) {
						$data[$parts[0]][$parts[1]] = $value;
					}
					else {
						$data[$parts[0]] = $value;
					}
				}
				else if (empty($value) && strcasecmp($field['compulsory'], 'Yes') == 0) {
					$this->updateSyncStatus('FAILED', 'The compulsory field ' . $field["field_name"] . ' is empty for channel SKU ' . $channelSkuId . '.');
					return false;
				}
			}
		}

		return $data;
	}

	private function processSkus($channelSkus, $withProduct = false, &$options = null) {
		$cfESRepo = new CustomFieldsRepository;
		$customFields = array();
		//$customFields = (count($channelSkus) > 0) ? $cfESRepo->getCF($channelSkus[0]->channel_id) : null;

		$skuInChannel = array();
		$options = array();
		foreach ($channelSkus as $channelSku) {
			$skuOptions = array();

			foreach($channelSku->sku_options as $option){
				if (array_key_exists($option->option_name, $skuOptions)) continue;

				if (count($skuOptions) > 2) break;

				$skuOptions[$option->option_name] = $option->option_value;

				if ($withProduct) {
					if (empty($options[$option->option_name])) {
						$options[$option->option_name][] = $option->option_value;
					}
					else if (!in_array($option->option_value, $options[$option->option_name])) {
						$options[$option->option_name][] = $option->option_value;
					}
				}
			}

			$skuInChannel[$channelSku->channel_sku_id] = array(
				'channel_sku_id'	=> $channelSku->channel_sku_id,
				'sku_id'			=> $channelSku->sku->sku_id,
				'hubwire_sku'		=> $channelSku->sku->hubwire_sku,
				'unit_price'		=> $channelSku->channel_sku_price,
				'sale_price'		=> $channelSku->channel_sku_promo_price,
				'sale_start_date'	=> $channelSku->promo_start_date,
				'sale_end_date'		=> $channelSku->promo_end_date,
				'quantity'			=> $channelSku->channel_sku_quantity,
				'options'			=> $skuOptions,
				'weight'			=> $channelSku->sku->sku_weight,
				'channel_sku_ref'	=> !empty($channelSku->ref_id) ? $channelSku->ref_id : null,
				'is_active'			=> $channelSku->channel_sku_active ? true : false,
				'custom_fields'		=> !empty($customFields) ? $this->getCustomFieldsData($channelSku->channel_sku_id, $cfESRepo, $customFields) : null
			);
		}

		return $skuInChannel;
	}

	private function processImages($media, $trashedImages = false) {
		$images = array();
		$relation = ($trashedImages) ? 'media_trashed' : 'media';

		foreach ($media as $image) {
			$mediaThirdParty = $this->getProductMediaThirdParty($image->id, $this->channel->id);

			$path = $image->{$relation}->media_url;
			if(strrpos($path, $image->{$relation}->ext) != false && strrpos($path, $image->{$relation}->ext) >= 0) {
				$path = substr($path, 0, (strlen($path) - strlen($image->{$relation}->ext)));
			}

			$filename = $image->{$relation}->filename;
			if(strrpos($filename, $image->{$relation}->ext) != false && strrpos($filename, $image->{$relation}->ext) >= 0) {
				$filename = substr($filename, 0, (strlen($filename) - strlen($image->{$relation}->ext)));
			}
			
			$images[] = array(
				'id'			=> $image->id,
				'path'			=> $path,
				'ext'			=> $image->{$relation}->ext,
				'filename'		=> $filename,
				'image_ref'		=> !is_null($mediaThirdParty) ? $mediaThirdParty->ref_id : null,
				'external_url'	=> !is_null($mediaThirdParty) ? $mediaThirdParty->external_url : null
			);
		}

		return $images;
	}

	private function getLelongStoreCategory($tags){
		$third_party = ThirdPartyCategory::where('channel_id','=', $this->channel->id)->get()->toArray();

		foreach($third_party as $menu){
			$menu_tags = !empty($menu['tags']) ? explode(',', $menu['tags']) : array();
			$result = array_intersect($menu_tags, array_unique($tags));

			if(count($result) == count($menu_tags)){
				return $menu['ref_id'];
			}
		}

		return null;
	}

	/*
	 *
	 * Gets info of third party
	 *
	 */
	public static function getProductThirdParty($productId, $channelId) {
		$productThirdParty = ProductThirdParty::where('channel_id', '=', $channelId)
												->where('product_id', '=', $productId)
												->first();

		return $productThirdParty;
	}

	public static function getProductMediaThirdParty($mediaId, $channelId) {
		$mediaThirdParty = ProductMediaThirdParty::where('media_id', '=', $mediaId)
													->where('channel_id', '=', $channelId)
													->first();

		return $mediaThirdParty;
	}

	/*
	 *
	 * Stores info of third party
	 *
	 */
	public static function storeProductThirdPartyInfo(array $info) {
		$productThirdParty = ProductThirdParty::firstOrNew(array('product_id' => $info['product_id'], 'channel_id' => $info['channel_id']));
		$productThirdParty->ref_id = $info['ref_id'];
		$productThirdParty->third_party_name = $info['third_party_name'];
		$productThirdParty->extra = !empty($info['extra']) ? $info['extra'] : '';
		$productThirdParty->save();
	}

	public static function storeSkuThirdPartyInfo(array $info) {
		$sku = SKU::where('hubwire_sku', '=', $info['hubwire_sku']);

		if(isset($info['product_id'])) {
			$sku = $sku->where('product_id', '=', $info['product_id']);
		}
		$sku = $sku->first();

		if (empty($sku)) {
			return false;
		}

		$channelSku = ChannelSKU::where('sku_id', '=', $sku->sku_id)
									->where('channel_id', '=', $info['channel_id'])
									->first();

		if (is_null($channelSku)) {
			return false;
		}

		$channelSku->ref_id = $info['ref_id'];
		$channelSku->save();

		return true;
	}

	public static function storeMediaThirdPartyInfo(array $info) {
		$mediaThirdParty = ProductMediaThirdParty::firstOrNew(array('media_id' => $info['media_id'], 'channel_id' => $info['channel_id']));
		$mediaThirdParty->ref_id = $info['ref_id'];
		$mediaThirdParty->third_party_name = $info['third_party_name'];

		if (!empty($info['external_url'])) {
			$mediaThirdParty->external_url = $info['external_url'];
		}

		$mediaThirdParty->save();
	}

	/*
	 *
	 * Other functions
	 *
	 */
	private function getProductId($sync) {
		switch($sync->ref_table){
			case 'ChannelSKU':
				$channelSku = ChannelSKU::findOrFail($sync->ref_table_id);
				$productId = $channelSku->product_id;
				break;
			case 'Product':
				$productId = $sync->ref_table_id;
				break;
			case 'ProductMedia':
				$productMedia = ProductMedia::withTrashed()->findOrFail($sync->ref_table_id);
				$productId = $productMedia->product_id;
				break;
			default:
				$productId = $sync->ref_table_id;
				break;
		}

		return $productId;
	}

	public function checkIsProductInMarketplace() {
		$productThirdParty = ProductSyncRepo::getProductThirdParty($this->productId, $this->channel->id);

		return !is_null($productThirdParty) ? true : false;
	}

	public function checkIsSkusInMarketplace() {
		$channelSkus = ChannelSKU::where('product_id', '=', $this->productId)
											->where('channel_id', '=', $this->channel->id)
											->get();

		$channelSkusNotInMarketplace = array();
		$syncIds = array();

		foreach ($channelSkus as $channelSku) {
			if (empty($channelSku->ref_id)) {
				$channelSkusNotInMarketplace[] = $channelSku->sku->hubwire_sku;

				$input['channel_sku_id'] = $channelSku->channel_sku_id;

				$syncRepo = new SyncRepository;
				$newSync = $syncRepo->updateSKU($input);
				$syncIds[] = $newSync->id;
			}
		}

		if (count($channelSkusNotInMarketplace) > 0) {
			$remarks = "ARC error: SKU not exist in marketplace: " . implode(', ', $channelSkusNotInMarketplace) . ".";
			$remarks .= " Refer to sync ID: " . implode(', ', $syncIds) . ".";

			$this->updateSyncStatus('FAILED', $remarks);

			return false;
		}
		else {
			return true;
		}
	}

	public static function checkHasActiveChanneSku($channelId, $productId) {
		$channelSkus = ChannelSKU::where('channel_id', '=', $channelId)
									->where('product_id', '=', $productId)
									->where('channel_sku_active', '=', 1)
									->get();

		return ($channelSkus->count() > 0) ? true : false;
	}

	private function updateSyncStatus($status, $remarks = null) {
		if (!is_null($remarks)) {
			$this->sync->remarks = $remarks;
		}

		$this->sync->status = strtoupper($status);
		$this->sync->save();

		$this->syncStatus = $status;
	}

	public static function updateSkuLivePrice($livePriceSkus) {
		foreach($livePriceSkus as $channelSkuId => $livePrice) {
			// \Log::info('Live Price for Channel SKU #' . $channelSkuId . ' updating to ' . $livePrice);
			ChannelSKU::where('channel_sku_id', $channelSkuId)->update(['channel_sku_live_price' => $livePrice]);
		}
	}
}
