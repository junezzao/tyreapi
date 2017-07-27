<?php

namespace App\Modules\Fulfillment\Repositories\Eloquent;

use App\Modules\Fulfillment\Repositories\Contracts\ReturnRepository as ReturnRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Models\Admin\ReturnLog;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelSKU;
use App\Events\ChannelSkuQuantityChange;
use App\Events\OrderUpdated;
use App\Repositories\RejectLogRepository as RejectRepo;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;

class ReturnRepository extends Repository implements ReturnRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    public function __construct(ReturnLog $model)
    {
        $this->model = $model;
    }

    /**
    * Specify Model class name
    *
    * @return mixed
    */
    public function model()
    {
        return 'App\Models\Admin\ReturnLog';
    }

    public function create(array $data)
    {
        // Inputs validations
        $v = \Validator::make($data, [
            'member_id' => 'integer',
            'user_id' => 'integer',
            'order_id' => 'integer|required',
            'order_item_id' => 'integer|required',
            'status' => 'required|in:In Transit,Restocked',
            'quantity' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0'
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $model = parent::create($data);
        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $v = \Validator::make($data, [
            'status' => 'required|in:Restocked,Rejected'
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        if($data['status'] == 'Restocked') {
            $data['completed_at'] = gmdate("Y-m-d H:i:s");
        }

        if (!empty($data['user_id'])) {
        	$userId = $data['user_id'];
        	unset($data['user_id']);
        }
        else {
        	$userId = 0;
        }

        if (!empty($data['remark'])) {
        	$rejectRemark = $data['remark'];
        	unset($data['remark']);
        }
        else {
        	$rejectRemark = 'Unknown';
        }

        $model = parent::update($data, $id, $attribute);

        $returnLog = $this->find($id);

        $order = Order::findOrFail($returnLog->order_id);
        $item = OrderItem::where('order_id', $returnLog->order_id)
                ->where('ref_type', 'ChannelSKU')
                ->findOrFail($returnLog->order_item_id);
        $channel_sku = ChannelSKU::where('channel_sku_id', $item->ref_id)->first();

        if($returnLog->status == 'Restocked') {
            if(!$this->fulfilled($order)) {
                $this->throwError('Unfulfilled item cannot be restocked.');
            }

            event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $returnLog->quantity, 'ReturnLog', $returnLog->id, 'increment'));
        } elseif ($returnLog->status == 'Rejected'){
            // create reject log
            $rejectLog = array(
                'user_id'       => $userId,
                'remarks'       => $rejectRemark,
                'sku_id'        => $channel_sku->sku_id,
                'channel_id'    => $channel_sku->channel_id,
                'quantity'      => $returnLog->quantity,
                'outbound'      => 1,
            );
            $rejectRepo = new RejectRepo;
            $rejectLog = $rejectRepo->create($rejectLog);

            event(new OrderUpdated($returnLog->order_id, 'Returned Item: Rejected', 'reject_log', $rejectLog->id, ['orderItemRefId' => $item->ref_id], $userId));
        }

        return $returnLog;
    }

    public function fulfilled($order){
        return $order->status >= Order::$shippedStatus ? true : false;
    }

    public function throwError($msg) {
        $errors = response()->json(array(
            'code' => 422,
            'error' => $msg
        ));

        throw new HttpResponseException($errors);
    }
}
