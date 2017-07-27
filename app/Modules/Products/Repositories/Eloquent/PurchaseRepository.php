<?php
namespace App\Modules\Products\Repositories\Eloquent;

use App\Modules\Products\Repositories\Contracts\PurchaseRepositoryContract as PurchaseRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\ChannelRepository as ChannelRepo;
use App\Repositories\SKURepository as SKURepo;
use App\Modules\Channels\Repositories\Eloquent\ChannelTypeRepository as ChannelTypeRepo;
use App\Repositories\Eloquent\SyncRepository;

use App\Models\Admin\ChannelType;
use App\Models\Admin\Purchase;
use App\Models\Admin\Channel;

use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use App\Events\ChannelSkuQuantityChange;



class PurchaseRepository extends Repository implements PurchaseRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    protected $channelSKURepo;

    protected $channelRepo;

    protected $channelTypeRepo;

    protected $skuRepo;

    protected $user_id;

    public function __construct(Purchase $model)
    {
        $this->model = $model;
        $this->channelSKURepo = new ChannelSKURepo;
        $this->channelRepo = new ChannelRepo(new Channel);
        $this->channelTypeRepo = new ChannelTypeRepo(new ChannelType);
        $this->skuRepo = new SKURepo;
        $this->user_id = Authorizer::getResourceOwnerId();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\Purchase';
    }

    public function create(array $data)
    {
        if($data['replenishment'] == 0 && !isset($data['channel_id'])){
          // get warehouse channel id
            $channelType = $this->channelTypeRepo->where('name','=','Warehouse')->first();
            $channel = $this->channelRepo
                ->getChannelsByMerchantAndType($data['merchant_id'], $channelType->id)
                ->first();
            if(!is_null($channel))
                $data['channel_id'] = $channel->id;
        }

        $this->validate($data);
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        unset($data['items'], $data['deleted_at']);
        $model = parent::create($data);

        Activity::log('Procurement '.$model->batch_id.' created', $this->user_id);

        return $this->with('items')->find($model->batch_id);
    }

    public function validate($data)
    {
      // Inputs validations
        $rules = [
           'batch_currency'  => 'required',
           'batch_conversion_rate' => 'required|numeric|min:0.00',
           'batch_remarks' => 'sometimes|string',
           'client_id' => 'required|integer|exists:clients',
           'batch_status' => 'required|integer|min:0',
           'replenishment' => 'required|boolean',
           'user_id' => 'required|integer|min:1|exists:users,id',
           'supplier_id' => 'sometimes|required|integer|min:1|exists:suppliers,id',
           'batch_tax' => 'sometimes|required|numeric|min:0.00',
           'batch_shipping' => 'sometimes|required|numeric|min:0.00',
           'channel_id' => 'required|integer|exists:channels,id',
           'batch_date' => 'required|date_format:Y-m-d',
           'merchant_id' => 'required|integer|min:1|exists:merchants,id',
           'receive_date' => 'sometimes|required|date_format:Y-m-d H:i:s',
        ];

        $messages = [];
        $messages['channel_id.required'] = 'No warehouse channel type found under this merchant';

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        if(!empty($data['items']))
        {
            $this->validate_items($data);
        }
    }

    public function validate_items($data)
    {
          
      $messages = [];
      $rules = [
            'items' => 'required|array'
           ];
      foreach($data['items'] as $key => $val)
      {
          if($data['replenishment']==1)
          {
            // $rules['items.'.$key.'.hubwire_sku'] = 'required_without:items.'.$key.'.sku_id|integer|exists:sku,hubwire_sku';
            $rules['items.'.$key.'.sku_id'] = 'required|integer|exists:sku,sku_id,merchant_id,'.$data['merchant_id'].',deleted_at,NULL|exists:channel_sku,sku_id,channel_id,'.$data['channel_id'];
            $rules['items.'.$key.'.item_retail_price'] = 'required|numeric|min:0.00';

            $messages['items.'.$key.'.sku_id.required'] = 'The sku id field is required.';
            $messages['items.'.$key.'.sku_id.exists'] = 'The selected sku id does not exists or has been deleted.';

          }
          else
          {
            // $rules['items.'.$key.'.product'] = 'required|alphanum';
            $rules['items.'.$key.'.name'] = 'required';
            $rules['items.'.$key.'.description'] = 'sometimes';
            $rules['items.'.$key.'.client_sku'] = 'unique:sku,hubwire_sku|string|max:100';
            $rules['items.'.$key.'.sku_supplier_code'] = 'string|max:100';
            $rules['items.'.$key.'.sku_weight'] = 'required|numeric|min:0';
            $rules['items.'.$key.'.prefix'] = 'required|exists:brands,prefix,merchant_id,'.(!empty($data['merchant_id'])?$data['merchant_id']:0);
            $rules['items.'.$key.'.tags'] = 'required|array';
            $rules['items.'.$key.'.option_name'] = 'required|array';
            $rules['items.'.$key.'.option_value'] = 'required|array';
            $rules['items.'.$key.'.item_retail_price'] = 'required|numeric|min:0.01';
            $rules['items.'.$key.'.category_id'] = 'required|exists:categories,full_name';

            $messages['items.'.$key.'.name.required'] = 'The product name field is required.';
            $messages['items.'.$key.'.client_sku.unique'] = 'The merchant sku cannot be the same as an existing Hubwire SKU.';
            $messages['items.'.$key.'.client_sku.max'] = 'The merchant sku cannot be greater than :max characters.';
            $messages['items.'.$key.'.sku_supplier_code.max'] = 'The sku supplier code may not be greater than :max characters.';
            $messages['items.'.$key.'.sku_weight.required'] = 'The sku weight field is required.';
            $messages['items.'.$key.'.sku_weight.numeric'] = 'The sku weight must be a number.';
            $messages['items.'.$key.'.sku_weight.min'] = 'The sku weight must be at least :min.';
            $messages['items.'.$key.'.prefix.required'] = 'The product brand field is required.';
            $messages['items.'.$key.'.prefix.exists'] = 'The selected product brand is invalid.';
            $messages['items.'.$key.'.tags.required'] = 'The sku tag field is required.';
            $messages['items.'.$key.'.tags.array'] = 'The sku tag must be an array.';
            $messages['items.'.$key.'.option_name.required'] = 'The option name field is required.';
            $messages['items.'.$key.'.option_name.array'] = 'The option name must be an array.';
            $messages['items.'.$key.'.option_value.required'] = 'The option value field is required.';
            $messages['items.'.$key.'.option_value.array'] = 'The option value must be an array.';
            $messages['items.'.$key.'.category_id.required'] = 'The category field is required.';
            $messages['items.'.$key.'.category_id.exists'] = 'The category is invalid.';

          }
          $rules['items.'.$key.'.item_cost'] = 'required|numeric|min:0.01';
          $rules['items.'.$key.'.item_quantity'] = 'required|integer|min:0';
          $rules['items.'.$key.'.created_at'] = 'sometimes|required|date_format:Y-m-d H:i:s';

          $messages['items.'.$key.'.item_cost.required'] = 'The item cost field is required.';
          $messages['items.'.$key.'.item_cost.numeric'] = 'The item cost must be a number.';
      }
      $v = \Validator::make($data, $rules, $messages);

      if ($v->fails()) {
          throw new ValidationException($v);
      }

    }

    public function update(array $data, $id, $attribute='batch_id')
    {
        // Inputs validations
        $batch = $this->find($id);

        $rules = [
           'batch_currency'  => 'sometimes|required',
           'batch_conversion_rate' => 'sometimes|required|numeric|min:0.00',
           'batch_remarks' => 'sometimes|required|string',
           'client_id' => 'sometimes|required|integer|exists:clients',
           'batch_status' => 'sometimes|required|integer|min:0',
           'replenishment' => 'sometimes|required|boolean',
           'user_id' => 'sometimes|required|integer|min:1|exists:users,id',
           'supplier_id' => 'sometimes|required|integer|min:1|exists:suppliers,id',
           'batch_tax' => 'sometimes|required|numeric|min:0.00',
           'batch_shipping' => 'sometimes|required|numeric|min:0.00',
           'channel_id' => 'sometimes|required|integer|exists:channels,id',
           'batch_date' => 'sometimes|required|date_format:Y-m-d',
           'receive_date' => 'sometimes|required|date_format:Y-m-d H:i:s',
           'items' => 'sometimes|required|array',
           'merchant_id' => 'sometimes|required|integer|min:1|exists:merchants,id',
        ];

        $messages = array();

        $channel_id = isset($data['channel_id'])?$data['channel_id']:$batch->channel_id;

        if(!empty($data['items']))
        {
            foreach($data['items'] as $key => $val)
            {
                if($data['replenishment']==1)
                {
                  // $rules['items.'.$key.'.hubwire_sku'] = 'required_without:items.'.$key.'.sku_id|integer|exists:sku,hubwire_sku';
                  $rules['items.'.$key.'.sku_id'] = 'required|integer|exists:sku,sku_id|exists:channel_sku,sku_id,channel_id,'.$channel_id;
                  $rules['items.'.$key.'.item_retail_price'] = 'required|numeric|min:0.00';

                  $messages['items.'.$key.'.sku_id.required'] = 'The sku id field is required.';
                  $messages['items.'.$key.'.sku_id.exists'] = 'The selected sku id is not exists.';
                }
                else
                {
                  $rules['items.'.$key.'.product'] = 'required';
                  $rules['items.'.$key.'.name'] = 'required';
                  $rules['items.'.$key.'.sku_weight'] = 'required|numeric|min:0';
                  $rules['items.'.$key.'.prefix'] = 'required|exists:brands,prefix,merchant_id,'.$data['merchant_id'];
                  $rules['items.'.$key.'.tags'] = 'required|array';
                  $rules['items.'.$key.'.option_name'] = 'required|array';
                  $rules['items.'.$key.'.option_value'] = 'required|array';
                  $rules['items.'.$key.'.item_retail_price'] = 'required|numeric|min:0.01';
                }
                $rules['items.'.$key.'.item_id'] = 'required|integer|min:1|exists:purchase_items';
                $rules['items.'.$key.'.item_cost'] = 'required|numeric|min:0.01';
                $rules['items.'.$key.'.item_quantity'] = 'required|integer|min:0';
                $rules['items.'.$key.'.created_at'] = 'sometimes|required|date_format:Y-m-d H:i:s';
            }
        }

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        unset($data['access_token']);
        unset($data['items']);

        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        $model = parent::update($data, $id, $attribute);
        Activity::log('Procurement '.$id.' was updated', $this->user_id);

        return $this->find($id);
    }

    public function delete($id)
    {
      $batch = $this->findOrFail($id);
      $rules = [
            'batch_status' => 'not_in:1',
        ];
        $messages = array('batch_status.not_in'=>'The selected procurement already set to Received.');

        $v = \Validator::make(['batch_status'=>$batch->batch_status], $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        // delete products created by procurement batch

        $deleted = $batch->delete($id);
        Activity::log('Procurement '.$id.' was deleted', $this->user_id);

        return $deleted;
    }

    public function receive($id)
    {
        \DB::beginTransaction();
        $batch = $this->with('items')->findOrFail($id);
        $rules = [
            'batch_status'  => 'not_in:1',
            'channel'       => 'required|array',
            'channel_id'    => 'exists:channels,id,deleted_at,NULL'
        ];

        $messages = array(
            'batch_status.not_in' => 'The selected procurement already set to Received.',
            'channel.required'    => 'Target channel not found.',
            'channel_id.exists'   => 'Unable to receive products because target channels has been deleted.',
        );

        $inputs = [
            'batch_status'    => $batch->batch_status, 
            'channel_id'      => $batch->channel_id,
            'items'           => $batch->items
        ];

        $channelType = $this->channelTypeRepo->where('name', '=', 'Warehouse')->first();

        if($batch->channel_id > 0)
          $channel = $this->channelRepo->find($batch->channel_id);
        else
          $channel = $this->channelRepo
                  ->getChannelsByMerchantAndType($batch->merchant_id, $channelType->id)
                  ->first();

        $inputs['channel'] = !empty($channel)?$channel->toArray():null;
        $input['merchant_id'] = $batch->merchant_id;

        if($batch->replenishment==1)
        { 
          foreach ($batch->items as $key => $item)
          {  
            $rules['items.'.$key.'.sku_id'] = 'required|integer|exists:channel_sku,sku_id,channel_id,'.$channel->id;
            $sku = $this->skuRepo->find($item->sku_id);
            $messages['items.'.$key.'.sku_id.exists'] = 'HW SKU ['.$sku->hubwire_sku.'] does not exist in target channel, please remove this product from the sheet to proceed.';
          }
        }

        $v = \Validator::make($inputs, $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $items = $batch->items;
        $errors[] = array();
        foreach ($batch->items as $item)
        {
            $channel_sku = new $this->channelSKURepo;
            if ($batch->replenishment == 1) {
                $channel_sku = $channel_sku
                                ->where('channel_id','=',$channel->id)
                                ->where('sku_id','=', $item->sku_id)
                                ->first();
            }
            else {
                $data = [
                    'channel_id'=>$channel->id,
                    'sku_id'=>$item->sku_id,
                    'product_id'=>$item->sku->product_id,
                    'merchant_id'=>$batch->merchant_id
                ];
                $channel_sku = $channel_sku->create($data);
            }

            // $old_qty = $channel_sku->channel_sku_quantity;

            // $channel_sku->increment('channel_sku_quantity', $item->item_quantity);
            // $channel_sku->touch();

            if (!isset($channel_sku->channel_sku_price)||$channel_sku->channel_sku_price == 0) {
              $channel_sku->channel_sku_price = $item->item_retail_price;
            }

            $channel_sku->save();

            // event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $old_qty, 'Purchase', $batch->batch_id));
            event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $item->item_quantity, 'Purchase', $batch->batch_id, 'increment'));
            unset($channel_sku);
        }

        if ($batch->replenishment == 1) {
            $syncRepo = new SyncRepository;
            $syncs = $syncRepo->restock($batch->batch_id);
        }

        $this->update(['batch_status' => 1, 'receive_date' => date('Y-m-d H:i:s')], $id);
        \DB::commit();
        $model  = $this->with('items')->find($id);
        Activity::log('Procurement ' . $id . ' was received', $this->user_id);

        return $model;
    }

    public function findBatch($batchId, $merchantId, $channelId) {
      $response['success'] = true;
      $items = \DB::select(\DB::raw(
        "SELECT *, (
            SELECT GROUP_CONCAT( CONCAT(option_name, ':', option_value)  SEPARATOR ',')
            FROM sku_combinations sc
            INNER JOIN sku_options so ON sc.option_id = so.option_id
            WHERE sc.sku_id = sku.sku_id
          ) as options, (
            SELECT GROUP_CONCAT( value SEPARATOR ',' )
            FROM product_tags st
            WHERE st.product_id = sku.product_id
            AND st.deleted_at IS NULL
          ) as tags
          FROM sku
          INNER JOIN purchase_items pi ON sku.sku_id = pi.sku_id
          INNER JOIN products on sku.product_id = products.id
          INNER JOIN channel_sku cs on cs.sku_id = pi.sku_id AND cs.channel_id = $channelId
          WHERE pi.deleted_at IS NULL
          AND sku.merchant_id = $merchantId
          AND cs.channel_sku_quantity > 0
          AND pi.batch_id = \"$batchId\"
          GROUP BY cs.channel_sku_id
          ORDER BY pi.updated_at DESC"
      ));

      $batch = $this->model->find($batchId);
      //$response['batch'] = $batch->toArray();
      $response['items'] = $items;
      //$response['count'] = count($items);
      //$response['success'] = true;
      return $response;
    }

    public function all($filters=array())
    {
        $channel_id = (isset($filters['channel_id']) && !empty($filters['channel_id'])) ? $filters['channel_id'] : '';

        if(!empty($channel_id))
        {
            $purchases = Purchase::with('itemCount', 'totalItemCost')->where('channel_id', $channel_id)->get();
        }
        else
        {
            $purchases = Purchase::with('itemCount', 'totalItemCost')->get();
        }
        return $purchases;
    }
}
