<?php
namespace App\Modules\Channels\Repositories\Eloquent;

use App\Repositories\Repository;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryContract;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\Merchant;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ThirdPartySyncArchive;
use App\Models\Admin\ThirdPartySyncLog;
use App\Models\Admin\ThirdPartyCategory;
use App\Models\Admin\Webhook;
use App\Repositories\OAuthRepository as OAuthRepo;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use DB;
use Log;
use Activity;

class ChannelRepository extends Repository implements ChannelRepositoryContract
{
    protected $model;

    protected $channelDetail;

    protected $merchant;

    protected $role;

    protected $skipCriteria = true;

    public function __construct(Channel $model, ChannelDetails $channelDetail, Merchant $merchant)
    {
        $this->model = $model;
        $this->channelDetail = $channelDetail;
        $this->merchant = $merchant;
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        $created = $this->model->create($data['channel']);

        $data['channel_detail']['channel_id'] = $created->id;
        if (!empty($data['channel_detail']['shipping_default']) && $data['channel_detail']['shipping_default'] == 1) {
            $channelTypeDetails = ChannelType::where('id', '=', $data['channel']['channel_type_id'])->first();
            $data['channel_detail']['shipping_rate'] = $channelTypeDetails['shipping_rate'];
        }
        $this->channelDetail->create($data['channel_detail']);

        $channel = $this->model->find($created->id);

        if (!empty($data['merchants'])) {
            $channel->merchants()->attach($data['merchants']);
        }

        return $this->model->with('channel_detail', 'merchants')->find($created->id);
    }

    /**
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id)
    {
        $channel = $this->model->with('merchants', 'channel_type')->find($id);

        $this->model->where('id', '=', $id)->update($data['channel']);

        if (!empty($data['channel_detail']['shipping_default']) && $data['channel_detail']['shipping_default'] == 1) {
            $data['channel_detail']['shipping_rate'] = $channel['channel_type']['shipping_rate'];
        }

        if (!empty($data['channel_detail'])) {
            $this->channelDetail->updateOrCreate(array('channel_id' => $id), $data['channel_detail']);
        }

        if (!empty($data['merchants'])) {
            $input_merchant = $data['merchants'];
            $current_merchants = array();

            foreach ($channel->merchants as $merchant) {
                $current_merchants[] = $merchant->id;
            }

            $new_merchants = array_diff($input_merchant, $current_merchants);
            $deleted_merchants = array_diff($current_merchants, $input_merchant);

            if (count($new_merchants) > 0) {
                $channel->merchants()->attach($new_merchants);
            }

            if (count($deleted_merchants) > 0) {
                $channel->merchants()->detach($deleted_merchants);
            }
        }else{
            // when no merchant input is empty, means in edit page, all merchant checkboxes are unticked, hence remove all relation
            $channel->merchants()->detach();
        }

        if (!empty($data['tags']) && strcasecmp($channel->channel_type->name, 'Lelong') == 0) {
            foreach ($data['tags'] as $categoryId => $tags) {
                ThirdPartyCategory::find($categoryId)->update(array('tags' => $tags));
            }
        }

        return $this->model->with('channel_detail', 'merchants')->find($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $channel = $this->model->with('merchants')->find($id);

        if (count($channel->merchants) > 0) {
            return "There are merchants using channel " . $channel->name;
        }
        else {
            $this->channelDetail->where('channel_id', '=', $id)->delete();
            return $this->model->destroy($id);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function byMerchant($merchant_id, $warehouse=null)
    {
        $data = Merchant::with('channels')->find($merchant_id);

        // filter out warehouse channels
        if (!is_null($warehouse)) {
            $channels = array();
            foreach($data->channels as $channel) {
                if($channel->channel_type_id!=12) {
                    $channels[] = $channel;
                }
            }
            unset($data->channels);
            $data->channels = $channels;
        }
        return $data;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function byMerchantWithTrashed($merchant_id, $warehouse=null)
    {
        $data = Merchant::with('channels')->withTrashed()->find($merchant_id);

        // filter out warehouse channels
        if (!is_null($warehouse)) {
            $channels = array();
            foreach($data->channels as $channel) {
                if($channel->channel_type_id!=12) {
                    $channels[] = $channel;
                }
            }
            unset($data->channels);
            $data->channels = $channels;
        }
        return $data;
    }


    public function getChannelsByMerchantAndType($merchantId, $chnlTypeId)
    {
        $channels = Channel::whereHas('merchants', function($q) use ($merchantId){
            $q->where('merchant_id', '=', $merchantId);
        })->where('channel_type_id', '=', $chnlTypeId)->get();

        //$channels = Channel::merchants()->where('channel_type_id', '=', $chnlTypeId);
        return $channels;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getSyncHistory($data) {
        $table = ($data['archived']) ? 'third_party_sync_archive' : 'third_party_sync';
        $model = ($data['archived']) ? new ThirdPartySyncArchive : new ThirdPartySync;

        $thirdPartySync = $model->with('logs')->select(DB::raw('CASE ' . $table . '.ref_table
                                                        WHEN "Product" THEN ' . $table . '.ref_table_id
                                                        WHEN "ChannelSKU" THEN channel_sku.product_id
                                                        WHEN "ProductMedia" THEN product_media.product_id END AS product_id'),
                                                        $table . '.id',
                                                        $table . '.action as event',
                                                        $table . '.trigger_event',
                                                        $table . '.status',
                                                        $table . '.sent_time',
                                                        $table . '.created_at',
                                                        $table . '.remarks')
                                    ->leftJoin('channel_sku', function($query) use ($table) {
                                        $query->on($table . '.ref_table_id', '=', 'channel_sku.channel_sku_id')
                                                ->where($table . '.ref_table', '=', 'ChannelSKU');
                                    })
                                    ->leftJoin('product_media', function($query) use ($table) {
                                        $query->on($table . '.ref_table_id', '=', 'product_media.id')
                                                ->where($table . '.ref_table', '=', 'ProductMedia');
                                    })
                                    ->where($table . '.channel_id', '=', $data['channel_id']);


        if (!empty($data['merchant_id'])) {
            $thirdPartySync = $thirdPartySync->where('merchant_id', '=', $data['merchant_id']);
        }

        if (!empty($data['search'])) {
            if (!empty($data['search']['product_id'])) {
                $thirdPartySync = $thirdPartySync->whereRaw('(CASE ' . $table . '.ref_table
                                        WHEN "Product" THEN ' . $table . '.ref_table_id
                                        WHEN "ChannelSKU" THEN channel_sku.product_id
                                        WHEN "ProductMedia" THEN product_media.product_id END) = ' . intval($data['search']['product_id']));
            }

            if (!empty($data['search']['sent_time'])) {
                $times = explode(' - ', $data['search']['sent_time']);
                $fromSentTime = $times[0];
                $toSentTime = date('Y-m-d', strtotime('+1 day', strtotime($times[1])));

                $thirdPartySync = $thirdPartySync->whereBetween($table . '.sent_time', [$fromSentTime, $toSentTime]);
            }

            if (!empty($data['search']['created_at'])) {
                $times = explode(' - ', $data['search']['created_at']);
                $fromCreatedAt = $times[0];
                $toCreatedAt = date('Y-m-d', strtotime('+1 day', strtotime($times[1])));

                 $thirdPartySync = $thirdPartySync->whereBetween($table . '.created_at', [$fromCreatedAt, $toCreatedAt]);
            }

            if (!empty($data['search']['id'])) {
                $thirdPartySync = $thirdPartySync->where($table . '.id', '=', intval($data['search']['id']));
            }

            if (!empty($data['search']['event'])) {
                $thirdPartySync = $thirdPartySync->where($table . '.action', 'like', '%' . $data['search']['event'] . '%');
            }

            if (!empty($data['search']['trigger_event'])) {
                $thirdPartySync = $thirdPartySync->where($table . '.trigger_event', 'like', '%' . $data['search']['trigger_event'] . '%');
            }

            if (!empty($data['search']['status'])) {
                $thirdPartySync = $thirdPartySync->where($table . '.status', '=', $data['search']['status']);
            }
        }

        $order = !empty($data['columns'][$data['order'][0]['column']]['name']) ? $data['columns'][$data['order'][0]['column']]['name'] : 'created_at';
        $order = (strcasecmp($order, 'product_id') == 0) ? DB::raw(1) : $table . '.' . $order;
        $dir = $data['order'][0]['dir'];

        $total = $thirdPartySync->count();

        $thirdPartySync = $thirdPartySync->skip((!empty($data['start']) ? $data['start'] : 0))
                                            ->take((!empty($data['length']) ? $data['length'] : 20))
                                            ->orderBy($order, $dir)
                                            ->get();

        return  array('total' => $total, 'sync_history' => $thirdPartySync);
    }

    public function getSyncStatusById($syncId) {
        $thirdPartySync = ThirdPartySync::select('status')->findOrFail($syncId);
        return $thirdPartySync->status;
    }

    public function updateSyncStatus($syncId, $status) {
        $thirdPartySync = ThirdPartySync::findOrFail($syncId);

        if (strcasecmp($status, 'RETRY') == 0) {
            $remarks = $thirdPartySync->remarks;

            if (!is_string($remarks) && unserialize($remarks) !== false) {
                $remarks = json_encode(unserialize($remarks));
            }

            ThirdPartySyncLog::create(array(
                'sync_id'       => $thirdPartySync->id,
                'request_id'    => $thirdPartySync->request_id,
                'status'        => $thirdPartySync->status,
                'remarks'       => $remarks,
                'sent_time'     => $thirdPartySync->sent_time
            ));
        }

        $thirdPartySync->status = strtoupper($status);
        $thirdPartySync->save();

        ThirdPartySync::updateSyncStatus($thirdPartySync);

        return true;
    }

    public function all($channel_id=null)
    {
        if(!is_null($channel_id) && !empty($channel_id))
        {
            $channels = array(Channel::with('channel_detail', 'channel_type', 'merchants','issuing_company')->find($channel_id));
        }
        else
        {
            $channels = Channel::with('channel_detail', 'channel_type', 'merchants','issuing_company')->get();
        }

        return $channels;
    }

    public function getWebhooks($channelId) {
        return Webhook::where('channel_id', '=', $channelId)->get();
    }

    public function deleteChannelWebhooks($channelId) {
        return Webhook::where('channel_id', '=', $channelId)->delete();
    }

    public function createWebhook(array $webhook, $channelId) {
        $new_webhook = new Webhook;
        $new_webhook->ref_id = $webhook['id'];
        $new_webhook->channel_id = $channelId;
        $new_webhook->format = $webhook['format'];
        $new_webhook->address = $webhook['address'];
        $new_webhook->topic = $webhook['topic'];
        $new_webhook->save();

        return Webhook::findOrFail($new_webhook->webhook_id);
    }

    public function getStoreCategories($channelId) {
        return ThirdPartyCategory::where('channel_id', '=', $channelId)->get();
    }

    public function updateStoreCategories($channelId, array $storeCategories) {
        $refIds = array_pluck($storeCategories, 'CategoryID');
        ThirdPartyCategory::where('channel_id', '=', $channelId)->whereNotIn('ref_id', $refIds)->delete();

        foreach ($storeCategories as $cat) {
            $tpCat = ThirdPartyCategory::firstOrNew(array('channel_id' => $channelId, 'ref_id' => $cat['CategoryID']));
            $tpCat->channel_id = $channelId;
            $tpCat->category_code = $cat['CategoryName'];
            $tpCat->ref_id = $cat['CategoryID'];

            $tpCat->save();
        }

        return true;
    }

    public function getStorefrontApi($channelId, $userId){
        $oauthRepo = new OAuthRepo;
        $oauth = $oauthRepo->where('authenticatable_type', '=', 'Channel')->findBy('authenticatable_id', $channelId);
        if(is_null($oauth)){
            $data = array(
                'id'                    => sprintf("%12d",floor(microtime()*(rand(100000,999999)*1000000))),
                'authenticatable_id'    => $channelId,
                'authenticatable_type'  => 'Channel',
                'secret'                => sha1(uniqid()),
            );
            $oauth = $oauthRepo->create($data);
            $oauth = $oauthRepo->where('authenticatable_type', '=', 'Channel')->findBy('authenticatable_id', $channelId);
            Activity::log('Storefront API has been set for channel ID ' . $channelId, $userId);
        }
        
        return $oauth;
    }

    public function bulkUpdateSyncStatus($action, $syncIds){
        $status['cancel'] = 'CANCELLED';
        $status['retry'] = 'RETRY';

        // validate syncs
        $syncCount = ThirdPartySync::whereIn('id', $syncIds)->where('status', '=', $status[$action])->get();

        if($syncCount->count() > 0){
            $errors =  response()->json(
             array(
                'code' =>  422,
                'error' => 'Unable to perform '.$action.' action on selected syncs.',
            ));
            throw new HttpResponseException($errors);
        }else{
            // update sync status
            foreach($syncIds as $syncId){
                $this->updateSyncStatus($syncId, $status[$action]);
            }

            return true;
        }
    }

    public function getIssuingCompany($channelId){
        $result =  DB::table('channels')->where('id', $channelId)->first();
        $result2 =  DB::table('issuing_companies')->where('id', $result->issuing_company)->first();
        
        return $result2;
    }
}