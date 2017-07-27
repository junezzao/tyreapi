<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\SKU;
use App\Models\Admin\Product;
use App\Models\Admin\QuantityLogApp;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\ReservedQuantityLog;
use Carbon\Carbon;

class SKURepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    public function __construct()
    {
        $this->model = new SKU;
        parent::__construct();
    }

    public function model()
    {
        return 'App\Models\Admin\SKU';
    }

    public function create(array $data)
    {
        $rules = [
           'client_sku'  => 'string',
           'product_id' => 'required',
           'merchant_id' => 'required|exists:merchants,id',
           'sku_supplier_code' => 'string',
           'hubwire_sku' => 'string',
           'sku_weight' => 'numeric',
           
        ];
        $messages = array();
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
        
        return $this->find($model->sku_id);
    }

    public function update(array $data, $id, $attribute='sku_id')
    {
        $rules = [
           'client_sku'  => 'sometimes|string',
           'product_id' => 'sometimes|required',
           'merchant_id' => 'sometimes|required|exists:merchants,id',
           'sku_supplier_code' => 'string',
           'hubwire_sku' => 'string',
           'sku_weight' => 'numeric',
           
        ];
        $messages = array();
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
        
        return $model;
    }

    public function getStockMovements($by, $id, $period = '60 days') {
        $now = strtotime('now');
        $start = date('Y-m-d', strtotime('-' . $period, $now)) . ' 00:00:00';

        $col = $by . '_id';

        $logs = QuantityLogApp::select('quantity_log_app.*')
                                ->with('channel_sku')
                                ->join('channel_sku', 'quantity_log_app.channel_sku_id', '=', 'channel_sku.channel_sku_id')
                                ->where('channel_sku.' . $col, '=', $id)
                                ->where('quantity_log_app.quantity_old', '<>', \DB::raw('quantity_log_app.quantity_new'))
                                ->where('quantity_log_app.created_at', '>=', $start)
                                ->orderBy('quantity_log_app.created_at', 'desc')
                                ->get();

        $reservedLogs = ReservedQuantityLog::select('reserved_quantities_log.*')
                                            ->with('channel_sku')
                                            ->join('channel_sku', 'reserved_quantities_log.channel_sku_id', '=', 'channel_sku.channel_sku_id')
                                            ->where('channel_sku.' . $col, '=', $id)
                                            ->where('reserved_quantities_log.quantity_old', '<>', \DB::raw('reserved_quantities_log.quantity_new'))
                                            ->where('reserved_quantities_log.created_at', '>=', $start)
                                            ->orderBy('reserved_quantities_log.created_at', 'desc')
                                            ->get();

        $transfersInTransit = DeliveryOrder::select('delivery_orders.*')
                                            ->join('delivery_orders_items', 'delivery_orders.id', '=', 'delivery_orders_items.do_id')
                                            ->join('sku', 'delivery_orders_items.sku_id', '=', 'sku.sku_id')
                                            ->where('delivery_orders.status', '=', 1)
                                            ->where('sku.' . $col, '=', $id)
                                            ->orderBy('delivery_orders.sent_at', 'desc')
                                            ->distinct()->get();

        $stockMovement = array();
        $do = array();
        $summary = array(
            'stock_in_hand'         => 0,
            'do_in_transit_stock'   => 0,
            'reserved_stock'        => 0,
            'available_stock'       => 0
        );

        foreach ($logs as $log) {
            $response = $this->getQuantityLogMessage($log, $do, $summary);

            if ($response !== false) {
                $stockMovement[] = $response;
            }
        }

        foreach ($reservedLogs as $log) {
            $response = $this->getReservedLogMessage($log, $summary);

            if ($response !== false) {
                $stockMovement[] = $response;
            }
        }

        foreach ($transfersInTransit as $doInTransit) {
            if ($by == 'sku') {
                $doInTransit->load(['items' => function ($query) use ($id) {
                    $query->where('sku_id', '=', $id);
                }]);
            }
            else {
                $doInTransit->load('items');
            }
            
            $doInTransit->load('originating_channel', 'target_channel');

            $response = $this->getStockTransterInTransitMessage($doInTransit, $summary);

            if ($response !== false) {
                $stockMovement = array_merge($stockMovement, $response);
            }
        }

        usort($stockMovement, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        $summary['available_stock'] = $summary['stock_in_hand'] - $summary['do_in_transit_stock'] - $summary['reserved_stock'];

        return array(
            'stock_movements' => $stockMovement,
            'summary' => $summary
        );
    }

    private function getQuantityLogMessage($log, &$do, &$summary) {
        $quantityDifference = $log->quantity_new - $log->quantity_old;
        $data = array(
            'skuId'                 => $log->channel_sku->sku->sku_id,
            'hubwireSku'            => $log->channel_sku->sku->hubwire_sku,
            'oldQuantity'           => $log->quantity_old,
            'newQuantity'           => $log->quantity_new,
            'quantityDifference'    => (($quantityDifference > 0) ? '+' : '-') . abs($quantityDifference),
        );

        $languageLine = 'stock_movement.';

        if (!is_null($log->ref) || $log->ref_table == 'Unknown') {
            switch ($log->ref_table) {
                case 'Purchase':
                    $log->ref->load('channel', 'items');

                    $languageLine .= ($log->ref->replenishment == 1) ? 'restocked' : 'created';

                    foreach ($log->ref->items as $purchaseItem) {
                        if ($purchaseItem->sku_id == $log->channel_sku->sku->sku_id) {
                            $data['quantity']   = $purchaseItem->item_quantity;
                        }
                    }

                    $data['batchId']            = $log->ref->batch_id;
                    $data['channelId']          = $log->ref->channel_id;
                    $data['channelName']        = $log->ref->channel->name;

                    $summary['stock_in_hand'] += $data['quantity'];
                    break;
                case 'RejectLog':
                    $log->ref->load('channel');

                    $languageLine .= 'rejected';

                    $data['rejectId']           = $log->ref->id;
                    $data['quantity']           = $log->ref->quantity;
                    $data['channelId']          = $log->ref->channel_id;
                    $data['channelName']        = $log->ref->channel->name;
                    $data['reason']             = $log->ref->remarks;

                    $summary['stock_in_hand'] -= $data['quantity'];
                    break;
                case 'DeliveryOrder':
                    if ($log->ref->status < 2) {
                        return false;
                    }

                    $log->ref->load('items', 'originating_channel', 'target_channel');
                    
                    $languageLine .= 'post_transfer';
                    $item = '';

                    foreach ($log->ref->items as $doItem) {
                        if ($doItem->sku_id == $log->channel_sku->sku->sku_id) {
                            $item = $doItem;
                            continue;
                        }
                    }

                    if (count($do) == 0) {
                        $do[$log->ref->id][] = $item->sku_id;
                    }
                    else {
                        foreach ($do as $key => $value) {
                            if (empty($do[$log->ref->id]) || (!empty($do[$log->ref->id]) && !in_array($item->sku_id, $do[$log->ref->id]))) {
                                $do[$log->ref->id][] = $item->sku_id;
                                break;
                            }
                            else {
                                return false;
                            }
                        }
                    }

                    $data['doId']                   = $log->ref->id;
                    $data['quantity']               = $item->quantity;
                    $data['originatingChannelId']   = $log->ref->originating_channel->id;
                    $data['originatingChannelName'] = $log->ref->originating_channel->name;
                    $data['targetChannelId']        = $log->ref->target_channel->id;
                    $data['targetChannelName']      = $log->ref->target_channel->name;
                    break;
                case 'Order':
                    $log->ref->load('items');

                    $languageLine .= 'sold';

                    foreach ($log->ref->items as $orderItem) {
                        if ($orderItem->ref_type == 'ChannelSKU' && $orderItem->ref_id == $log->channel_sku->channel_sku_id) {
                            $data['quantity']   = $orderItem->original_quantity;
                        }
                    }

                    $data['orderId'] = $log->ref->id;
                    break;
                case 'ReturnLog':
                    if ($log->ref->status == 'Restocked') {
                        $languageLine .= 'order_restocked';
                        $summary['stock_in_hand'] += $log->ref->quantity;
                    }
                    else if ($log->ref->status == 'Rejected') {
                        $languageLine .= 'order_rejected';
                        $data['reason'] = $log->ref->remark;
                    }
                    else {
                        return false;
                    }

                    $data['returnId']   = $log->ref->id;
                    $data['quantity']   = $log->ref->quantity;
                    $data['orderId']    = $log->ref->order_id;
                    break;
                case 'StockCorrection':
                    $languageLine .= 'stock_correction';
                    $summary['stock_in_hand'] += $quantityDifference;

                    $data['remark'] = $log->ref->remarks;
                    break;
                case 'ChannelSKU':
                case 'Unknown':
                    $languageLine .= 'unknown';
                    $summary['stock_in_hand'] += $quantityDifference;
                    break;
                default:
                    return false;
                    break;
            }
        }
        else {
            if ($log->ref_table == 'DeliveryOrder') {
                if (count($do) == 0) {
                    $do[$log->ref_table_id][] = $log->channel_sku->sku->sku_id;
                }
                else {
                    foreach ($do as $key => $value) {
                        if (empty($do[$log->ref_table_id]) || (!empty($do[$log->ref_table_id]) && !in_array($log->channel_sku->sku->sku_id, $do[$log->ref_table_id]))) {
                            $do[$log->ref_table_id][] = $log->channel_sku->sku->sku_id;
                            break;
                        }
                        else {
                            return false;
                        }
                    }
                }
            }

            $languageLine .= 'missing';
            $summary['stock_in_hand'] += $quantityDifference;

            $data['refTable']       = $log->ref_table;
            $data['refId']          = $log->ref_table_id;
        }

        return array(
                    'date'      => is_string($log->created_at) ? $log->created_at : $log->created_at->toDateTimeString(),
                    'event'     => $log->ref_table,
                    'message'   => trans($languageLine, $data)
                );
    }

    private function getReservedLogMessage($log, &$summary) {
        $quantityDifference = $log->quantity_new - $log->quantity_old;

        $data = array(
            'skuId'                 => $log->channel_sku->sku->sku_id,
            'hubwireSku'            => $log->channel_sku->sku->hubwire_sku,
            'oldQuantity'           => $log->quantity_old,
            'newQuantity'           => $log->quantity_new,
            'quantity'              => abs($quantityDifference),
            'orderId'               => $log->order_id,
        );
        
        $summary['reserved_stock'] += $quantityDifference;
        $summary['stock_in_hand']  -= ($quantityDifference > 0) ? 0 : abs($quantityDifference);

        $languageLine = 'stock_movement.' . (($quantityDifference > 0) ? 'reserved' : 'reserved_fulfilled');

        return array(
                    'date'      => is_string($log->created_at) ? $log->created_at : $log->created_at->toDateTimeString(),
                    'event'     => ($quantityDifference > 0) ? 'Reserved' : 'Fulfilled',
                    'message'   => trans($languageLine, $data)
                );
    }

    private function getStockTransterInTransitMessage($doInTransit, &$summary) {
        $itemMessages = array();
        
        foreach ($doInTransit->items as $item) {
            $data = array(
                'skuId'                     => $item->channel_sku->sku->sku_id,
                'hubwireSku'                => $item->channel_sku->sku->hubwire_sku,
                'quantity'                  => $item->quantity,
                'doId'                      => $doInTransit->id,
                'originatingChannelId'      => $doInTransit->originating_channel->id,
                'originatingChannelName'    => $doInTransit->originating_channel->name,
                'targetChannelId'           => $doInTransit->target_channel->id,
                'targetChannelName'         => $doInTransit->target_channel->name,
            );
            
            $summary['do_in_transit_stock'] += $data['quantity'];

            $itemMessages[] = array(
                'date'      => is_string($doInTransit->sent_at) ? $doInTransit->sent_at : $doInTransit->sent_at->toDateTimeString(),
                'event'     => 'DO In Transit',
                'message'   => trans('stock_movement.pre_transfer', $data)
            );
        }

        return $itemMessages;
    }
}
