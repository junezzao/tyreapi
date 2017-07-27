<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use App\Events\ChannelSkuQuantityChange;
use App\Repositories\Eloquent\SyncRepository;
use App\Repositories\RejectLogRepository as RejectRepo;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use App\Models\Admin\RejectLog;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;

class ChannelSKURepository extends Repository
{
	protected $model;

    protected $purchaseRepo;

    protected $role;

    protected $skipCriteria = false;

    protected $user_id;

    public function __construct()
    {
        $this->model = new ChannelSKU;
        $this->user_id = Authorizer::getResourceOwnerId();
        parent::__construct();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\ChannelSKU';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'sku_id' => 'required|integer|exists:sku',
           'product_id' => 'required|integer|exists:products,id',
           'channel_id' => 'required|integer|exists:channels,id',
           'client_id' => 'sometimes|integer|exists:clients',
           'merchant_id' => 'required|integer|exists:merchants,id',
           'channel_sku_quantity' => 'sometimes|required|integer|min:1',
           'channel_sku_min_quantity' => 'sometimes|required|integer|min:0',
           'channel_sku_price' => 'sometimes|required|numeric|min:0.00',
           'channel_sku_promo_price' => 'sometimes|required|numeric|min:0.00',
           'promo_start_date' => 'sometimes|required_unless:channel_sku_promo_price,0|date_format:Y-m-d',
           'promo_end_date' => 'sometimes|required_unless:channel_sku_promo_price,0|date_format:Y-m-d',
           'channel_sku_coordinates' => 'sometimes|required',
           'channel_sku_active' => 'sometimes|required|boolean',

        ];

        $messages = [];

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }

        $data = $newinputs;
        $model = parent::create($data);

        return $model;
    }

    public function update(array $data, $id, $attribute='channel_sku_id')
    {
      // Inputs validations
        // \Log::info(print_r($data, true));
        $rules = [
           'sku_id' => 'sometimes|required|integer|exists:sku',
           'product_id' => 'sometimes|required|integer|exists:products',
           'channel_id' => 'sometimes|required|integer|exists:channels',
           'client_id' => 'sometimes|required|integer|exists:clients',
           'channel_sku_quantity' => 'sometimes|required|integer|min:0',
           'channel_sku_min_quantity' => 'sometimes|required|integer|min:0',
           'channel_sku_price' => 'sometimes|required|numeric|min:0.00',
           'channel_sku_promo_price' => 'sometimes|required|numeric|min:0.00',
           'promo_start_date' => 'sometimes|required_unless:channel_sku_promo_price,0|before:promo_end_date',
           'promo_end_date' => 'sometimes|required_unless:channel_sku_promo_price,0|after:promo_start_date',
           'channel_sku_active' => 'sometimes|required|boolean',
        ];

        $messages = [];

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }

        $data = $newinputs;
        $ack = parent::update($data, $id, $attribute);
        $model = $this->find($id);

        $syncRepo = new SyncRepository;
        $input['channel_sku_id'] = $id;
        $sync = $syncRepo->updateSKU($input);

        return $model;
    }

    public function bulkReject(array $data){
        \DB::beginTransaction();
        foreach($data as $sku){
            $total_quantity = ChannelSKU::where('sku_id','=', $sku['sku_id'])
                ->where('channel_id','=', $sku['channel_id'])->pluck('channel_sku_quantity');
            $rules = [
                'qty'=>'numeric|min:1|max:'.$total_quantity[0],
                'channel_id'=>'numeric|min:1|exists:channels,id',
                'sku_id'=>'numeric|min:1|exists:sku,sku_id',
                'remarks'=>'required'
            ];

            $v = \Validator::make($sku, $rules);

            if ($v->fails()) {
                throw new ValidationException($v);
            }

            if($total_quantity[0] >= $sku['qty']){
                // Update Quantity
                $channel_sku = ChannelSKU::where('sku_id','=', $sku['sku_id'])
                    ->where('channel_id','=', $sku['channel_id'])->first();
                //$originating_channel = Channel::with('channel_group')->findOrFail($channel_sku->channel_id);
                // $oldQuantity = $channel_sku->channel_sku_quantity;
                // $channel_sku->decrement('channel_sku_quantity', $sku['qty']);
                // $channel_sku->touch();

                // check the reject reason
                $getReason = config('globals.reject_sku.reasons');
                $getReason = array_diff($getReason, array('Quantity adjustment'));
                $reason = 0;
                foreach ($getReason as $value) {
                    if($sku['remarks'] == $value) $reason = 1;
                }
                // log who did this 
                $newLog = array(
                  'user_id'       => $this->user_id,
                  'remarks'       => $sku['remarks'],
                  'sku_id'        => $sku['sku_id'],
                  'channel_id'    => $sku['channel_id'],
                  'quantity'      => $sku['qty'],
                  'outbound'      => $reason,
                );
                $rejectRepo = new RejectRepo;
                $rejectLog = $rejectRepo->create($newLog);
                // $log = new RejectLog;
                // $log->user_id = $this->user_id;

                // $log->remarks = $sku['remarks'];
                // $log->sku_id = $sku['sku_id'];
                // $log->channel_id = $sku['channel_id'];
                // $log->quantity = $sku['qty'];
                // $log->save();
                Activity::log('Rejected '.$sku['qty'].' of SKU ID: ['.$sku['sku_id'].'] from channel ID: ['.$sku['channel_id'].'].', $this->user_id);
                // Event::fire('appQuantityLog.updateChannelSku', array($channel_sku, 'decrement', $log->id, 'RejectLog'));

                // event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $oldQuantity, 'RejectLog', $rejectLog->id));
                event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $sku['qty'], 'RejectLog', $rejectLog->id, 'decrement'));
                $syncRepo = new SyncRepository;
                $input['channel_sku_id'] = $channel_sku->channel_sku_id;
                $input['trigger_event'] = 'RejectLog'.sprintf(' #%06d',$rejectLog->id);
                $sync = $syncRepo->updateQuantity($input);

                //$input = array();
                /*************** Third Party Sync *************/
                // Enter third party sync code here           //
                /************* End Third Party Sync ***********/
            }
        }
        \DB::commit();
        $response['success'] = true;
        return $response;
    }
}
