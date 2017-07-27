<?php
namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryContract as ChannelRepository;
use App\Repositories\ProductRepository;
use App\Modules\Channels\Repositories\Criteria\ByMerchant;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;

use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;
use DB;
use Log;
class ChannelController extends Controller
{
    protected $channelRepo;

    protected $authorizer;

    public function __construct(ChannelRepository $channelRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->channelRepo = $channelRepo;
        $this->authorizer = $authorizer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!empty(request()->get('merchant_id', ''))) {
            $channels = $this->channelRepo->byMerchant(request()->get('merchant_id'))->channels;
        }
        else {
            $channel_id = request()->get('channel_id', null);
            $channels = $this->channelRepo->all($channel_id);
        }
        return response()->json($channels);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $channel = $this->channelRepo->create($request->all());
        Activity::log('New channel (' . $channel->name . ' - ' . $channel->id . ') has been created.', $this->authorizer->getResourceOwnerId());

        return response()->json($channel);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $channel = $this->channelRepo->with('channel_detail', 'channel_type', 'merchants', 'oauth_client')->findOrFail($id);

        if (strcasecmp($channel->channel_type->name, 'Shopify') == 0 ) {
            $channel->webhooks = $this->channelRepo->getWebhooks($id);
        }elseif (strcasecmp($channel->channel_type->name, 'Shopify POS') == 0 ) {
            $channel->webhooks = $this->channelRepo->getWebhooks($id);
        }

        return response()->json($channel);
    }

    public function showWithTrashed($id)
    {
        $channel = $this->channelRepo->with('channel_detail', 'channel_type', 'merchants')->withTrashFind($id);

        if (strcasecmp($channel->channel_type->name, 'Shopify') == 0) {
            $channel->webhooks = $this->channelRepo->getWebhooks($id);
        }elseif (strcasecmp($channel->channel_type->name, 'Shopify POS') == 0 ) {
            $channel->webhooks = $this->channelRepo->getWebhooks($id);
        }

        return response()->json($channel);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $channel = $this->channelRepo->update($request->all(), $id);
        Activity::log('Channel ' . $id . ' has been updated.', $this->authorizer->getResourceOwnerId());

        return response()->json($channel);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $response = $this->channelRepo->delete($id);

        if ($response == 1) {
            Activity::log('Channel ' . $id . ' has been deleted.', $this->authorizer->getResourceOwnerId());
        }

        return response()->json(['response' => ($response == 1) ? true : $response]);
    }


    public function byMerchantAndChnlType($merchantId, $chnlTypeId)
    {

        return response()->json($this->channelRepo->getChannelsByMerchantAndType($merchantId, $chnlTypeId));
    }

    public function byMerchant(Request $request, $merchant_id)

    {
        //dd($request->get('warehouse'));
        return response()->json($this->channelRepo->byMerchant($merchant_id, $request->get('warehouse')));
    }

    public function byMerchantWithTrashed(Request $request, $merchant_id)
    {
        //dd($request->get('warehouse'));
        return response()->json($this->channelRepo->byMerchantWithTrashed($merchant_id, $request->get('warehouse')));
    }

    public function getSyncHistory(Request $request) {
        $syncHistory = $this->channelRepo->getSyncHistory($request->all());
        return response()->json($syncHistory);
    }

    public function retrySync($syncId) {
        $return = $this->updateSyncStatus($syncId, 'RETRY');
        
        return response()->json($return);
    }

    public function cancelSync($syncId) {
        $return = $this->updateSyncStatus($syncId, 'CANCELLED');
        
        return response()->json($return);
    }

    private function updateSyncStatus($syncId, $status) {
        if (strcasecmp($this->channelRepo->getSyncStatusById($syncId), $status) == 0) {
            $return['success'] = false;
            $return['message'] = 'Sync status is already in ' . strtoupper($status);
        }

        $response = $this->channelRepo->updateSyncStatus($syncId, strtoupper($status));

        if ($response !== true) {
            $return['success'] = false;
            $return['message'] = 'Failed to update sync status. Please try again later.';
        }
        else {
            $return['success'] = true;
            $return['message'] = 'Sync status successfully updated.';
        }

        return $return;
    }

    public function bulkUpdateSyncStatus(Request $request){
        $syncIds = explode(',', $request->get('sync-ids'));
        $action = $request->get('action');

        $response = $this->channelRepo->bulkUpdateSyncStatus($action, $syncIds);

        return response()->json($response);
    }

    public function getStoreCategories($channelId) {
        $storeCategories = $this->channelRepo->getStoreCategories($channelId);

        $productRepo = new ProductRepository;
        $tags = $productRepo->getExistingTags();

        return response()->json(array('store_categories' => $storeCategories, 'tags' => $tags));
    }

    public function getStorefrontApi($id){
        $userId = $this->authorizer->getResourceOwnerId();
        $storefrontApi = $this->channelRepo->getStorefrontApi($id, $userId);

        return response()->json($storefrontApi);
    }
    
    public function getBulkChannels(Request $request)
    {
        $channels = $this->channelRepo->whereIn('id', $request->get('channel_id'));

        return response()->json($channels);
    }

    public function getShippingProvider(Request $request){
        $channel_id = $request->id;
        $controller = $request->channel_type['controller'];

        $order_proc = new OrderProc($channel_id, new $controller);
        $response = $order_proc->getShippingProvider($request);
        if($response['success']==true){
            $channel_details = DB::table('channel_details')->get();
            foreach ($channel_details as $detail) {
                $detail_extra_info = json_decode($detail->extra_info, true);
                    if($detail->channel_id==$channel_id){
                        $detail_extra_info['shipping_provider'] = $response['name'];
                        if(isset($response['cod'])){
                            $detail_extra_info['shipping_provider_cod'] = $response['cod'];
                        }
                        $updateToDB = DB::table('channel_details')->where('channel_id', '=', $channel_id)->update(['extra_info' => json_encode($detail_extra_info)]);
                        
                    }
                
            }
        }
        $result['success'] = $response['success'];
        return response()->json($result);
    }
}
