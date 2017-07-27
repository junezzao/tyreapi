<?php
namespace App\Modules\ThirdParty\Http\Controllers;

use App\Modules\ThirdParty\Http\Controllers\ProductProcessingService as ProductProc;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Config;
use App\Http\Controllers\Admin\AdminController;
use App\Modules\Channels\Repositories\Eloquent\ChannelRepository;

use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\Merchant;

use stdClass;
use Log;
use Request;
use Response;
use Input;

class ThirdPartyController extends AdminController
{
	public function processSync($sync) {
		
		if(strtolower($sync->status) !== 'new' && strtolower($sync->status) !== 'retry') return;
		$controller = ChannelType::where('id', $sync->channel_type_id)->firstOrFail()->controller;

		switch($sync->action) {
			case 'createSKU':
			case 'updateSKU':
			case 'deleteSKU':
			case 'updateQuantity':
			case 'updateVisibility':
			case 'createProduct':
			case 'updateProduct':
			case 'deleteProduct':
			case 'uploadNewMedia':
			case 'setDefaultMedia':
			case 'deleteMedia':
			case 'updateMedia':
			case 'updatePrice':
				$productProc = new ProductProc($sync->channel_id, new $controller, $sync);
				$productProc->processSync($sync);
				break;
			case 'readyToShip':
			case 'getDocument':
			case 'feedStatus':
			case 'createOrderFulfillment':
				return;
			case 'createItemRefund':
				$orderProc = new OrderProc($sync->channel_id, new $controller, null, $sync);
				$orderProc->processRefund();
				break;
			case 'orderCreated':
			case 'orderUpdated':
				$orderProc = new OrderProc($sync->channel_id, new $controller, null, $sync);
				$orderProc->pushOrder();
			break;
			default:
				$this->sync->remarks = 'Action not found.';
				$this->sync->status = 'FAILED';
				$this->sync->save();
				break;
		}
	}

	public function processSyncInBulk($bulkSync = array()){
		$controller = ChannelType::where('id', $bulkSync['channelTypeId'])->firstOrFail()->controller;
		switch($bulkSync['action']){
			case 'bulkCreate':
			case 'bulkUpdate':
			case 'bulkMedia':
			case 'bulkQty':
			case 'bulkPrice':
				$productProc = new ProductProc($bulkSync['channelId'], new $controller, null, true);
				$productProc->processBulkSync($bulkSync);
				break;
			default:
				// handle error for action not found
				break;			
		}
	}

	public function getImageDimensions() {
		return response()->json(Config::get('marketplace.image_size'));
	}

	public function registerWebhooks($channelId) {
		$channel = Channel::with('channel_type', 'channel_detail')->findOrFail($channelId);

		$controller = new $channel->channel_type->controller;
		$controller->initialize($channel);

		$response = json_decode($controller->registerWebhooks()->content(), true);

		if ($response['success']) {
			$channelRepo = new ChannelRepository(new Channel, new ChannelDetails, new Merchant);
			$channelRepo->deleteChannelWebhooks($channel->id);
			$responses = json_decode($response['responses'],true);
			foreach ($responses as $webhook) {
				$newWebhook = $channelRepo->createWebhook($webhook, $channel->id);
			}
		}

		$return['success'] = $response['success'];
		$return['message'] = !empty($response['error']) ? $response['error'] : '';

		return response()->json($return);
	}

	public function feedStatus($sync) {
		$allowed_sync_statuses = array('QUEUED', 'PROCESSING', 'SENT');
		if(!in_array($sync->status, $allowed_sync_statuses)) return;

		$controller = ChannelType::where('id', $sync->channel_type_id)->firstOrFail()->controller;
		$channel = Channel::find($sync->channel_id)->firstOrFail();
		$thirdParty = new $controller;
		$thirdParty->initialize($channel, $sync);
		$thirdParty->feedStatus();
	}

	public function bulkFeedStatus($bulkSync) {
		$controller = ChannelType::where('id', $bulkSync['channelTypeId'])->firstOrFail()->controller;
		$channel = Channel::find($bulkSync['channelId'])->firstOrFail();
		$thirdParty = new $controller;
		$thirdParty->initialize($channel);
		$thirdParty->bulkFeedStatus($bulkSync);
	}

	public function importStoreCategories($channelId) {
		$channel = Channel::with('channel_type', 'channel_detail')->findOrFail($channelId);

		$controller = new $channel->channel_type->controller;
		$controller->initialize($channel);
		$response = json_decode($controller->importStoreCategories()->content(), true);

		if ($response['success']) {
			$channelRepo = new ChannelRepository(new Channel, new ChannelDetails, new Merchant);
			$channelRepo->updateStoreCategories($channelId, json_decode($response['response'], true)['StoreCategory']);
		}

		$return['success'] = $response['success'];
		$return['message'] = !empty($response['error']) ? $response['error'] : '';

		return response()->json($return);
	}
	public function getCategories($channel_type_id)
    {
    	// get first channel with this channel type
        $channel = Channel::with('channel_type', 'channel_detail')->where('channel_type_id', $channel_type_id)->where('status', 'Active')->firstOrFail();
        $controller = new $channel->channel_type->controller;
		$controller->initialize($channel);
        $response = $controller->getCategories();
        return $response;
    }
}

?>
