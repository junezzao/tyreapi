<?php namespace App\Modules\Contracts\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Eloquent\OrderItemRepository;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Contract;
use App\Models\Admin\ContractRule;
use App\Models\Admin\ChannelContract;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\SKU;
use App\Models\Admin\Merchant;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class ContractRepository extends Repository
{
    protected $model;

    protected $rule;

    protected $role;

    protected $skipCriteria = true;

    protected $user_id;


    public function __construct(Contract $model, ContractRule $rule)
    {
        $this->model = $model;
        $this->rule = $rule;
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\Contract';
    }

    public function store(array $data)
    {
        // Inputs validations
        $rules = [
            'contract.name'          => 'required',
            'contract.brand_id'      => 'required|integer|exists:brands,id',
            'contract.merchant_id'   => 'required|integer|exists:merchants,id',
            'contract.guarantee'     => 'sometimes|numeric',
            'contract.start_date'    => 'required|date_format:Y-m-d',
            'contract.end_date'      => 'sometimes|date_format:Y-m-d|after:contract.start_date',
            'contract.min_guarantee' => 'required|boolean',
            'contract.storage_fee'   => 'sometimes|numeric|min:0',
            'contract.inbound_fee'   => 'sometimes|numeric|min:0',
            'contract.outbound_fee'   => 'sometimes|numeric|min:0',

        ];

        foreach ($data['rule'] as $rule_id => $contract_rule) {
            //validation extand
            \Validator::extend('greater_than', function($attribute, $value, $parameters)
            {
                return $value >= $parameters[0];
            });

            if(empty($contract_rule['min_amount'])){
                $minAmount = 0;
            }else{
                $minAmount = $contract_rule['min_amount'];
            }

            $rules += [
                'rule.'.$rule_id.'.type'          =>'required',
                'rule.'.$rule_id.'.type_amount'   =>'required|numeric',
                'rule.'.$rule_id.'.base'          =>'required',
                'rule.'.$rule_id.'.operand'       =>'required',
                'rule.'.$rule_id.'.min_amount'    =>'sometimes|numeric',
                'rule.'.$rule_id.'.max_amount'    =>'sometimes:operand,Between|numeric|greater_than:'.$minAmount,
                'rule.'.$rule_id.'.fixed_charge'  =>'required|boolean',
            ];

            if (isset($contract_rule['categories'])) {
                foreach ($contract_rule['categories'] as $category) {
                    $rules['rule.'.$rule_id.'.categories'] = 'required|exists:categories,id';
                }
            }
            if (isset($contract_rule['channels'])) {
               foreach ($contract_rule['channels'] as $channel) {
                    $rules['rule.'.$rule_id.'.channels'] = 'required|exists:channels,id';
                }
            }
            if (isset($contract_rule['products'])) {
               foreach ($contract_rule['products'] as $product) {
                    $rules['rule.'.$rule_id.'.products'] = 'required|exists:products,id';
                }
            }
        }

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
        $existingContracts = Contract::where('brand_id', '=', $data['contract']['brand_id'])->where('merchant_id', '=', $data['contract']['merchant_id'])->get();
        $return = array();

        if($existingContracts->count() > 0){
            // check if there is conflicting date
            $startDate = $data['contract']['start_date'];
            $endDate = (!empty($data['contract']['end_date']))?$data['contract']['end_date']:false;

            $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
            $startDate->hour = 00;
            $startDate->minute = 00;
            $startDate->second = 00;

            if($endDate){
                $endDate = Carbon::createFromFormat('Y-m-d', $endDate);
                $endDate->hour = 23;
                $endDate->minute = 59;
                $endDate->second = 59;
            }

            $duplicateContracts = array();

            foreach ($existingContracts as $contract) {
                if(!empty($contract->start_date)){
                    $contractStartDate = Carbon::createFromFormat('Y-m-d', $contract->start_date);
                    if(is_null($contract->end_date)){
                        // no end date is set, check if start date is after contract's start date
                        if($startDate->gte($contractStartDate)
                            || ($endDate && $endDate->gte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractStartDate))){
                            // compare start date
                            $duplicateContracts[] = $contract->id;
                        }
                    }else{
                        $contractEndDate = Carbon::createFromFormat('Y-m-d', $contract->end_date);
                        // check if start and end date is in between
                        if($startDate->between($contractStartDate, $contractEndDate)
                            || ($endDate && $endDate->between($contractStartDate, $contractEndDate))
                            || ($endDate && $contractStartDate->between($startDate, $endDate))
                            || ($endDate && $contractEndDate->between($startDate, $endDate))
                            || (!$endDate && $startDate->lte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractEndDate))){
                            $duplicateContracts[] = $contract->id;
                        }
                    }
                }
            }

            if(count($duplicateContracts) > 0){
                $return['duplicate'] = $this->whereIn('id', $duplicateContracts);
                $return['error'] = true;

                return $return;
            }
        }

        // create contract
        $create = $this->model->create($data['contract']);
        foreach ($data['rule'] as $rule_id => $contract_rule) {
            if (isset($contract_rule['categories'])) {
               $contract_rule['categories'] = json_encode($contract_rule['categories']);
            }
            if (isset($contract_rule['channels'])) {
               $contract_rule['channels'] = json_encode($contract_rule['channels']);
            }
            if (isset($contract_rule['products'])) {
               $contract_rule['products'] = json_encode($contract_rule['products']);
            }
            $contract_rule['contract_id'] = $create->id;
            $createRule = $this->rule->create($contract_rule);
        }

        Activity::log('Contract '.$create->id.' was created', $this->user_id);

        $return['contract_index'] = $this->find($createRule->contract_id);

        return $return;
    }

    public function update(array $data, $id)
    {
        // Inputs validations
        $rules = [
            'contract.name'          => 'required',
            'contract.brand_id'      => 'required|integer|exists:brands,id',
            'contract.merchant_id'   => 'required|integer|exists:merchants,id',
            'contract.guarantee'     => 'sometimes|numeric',
            'contract.start_date'    => 'required|date_format:Y-m-d',
            'contract.end_date'      => 'sometimes|date_format:Y-m-d|after:contract.start_date',
            'contract.min_guarantee' => 'required|boolean',
            'contract.storage_fee'   => 'sometimes|numeric|min:0',
            'contract.inbound_fee'   => 'sometimes|numeric|min:0',
            'contract.outbound_fee'   => 'sometimes|numeric|min:0',
        ];

        foreach ($data['rule'] as $rule_id => $contract_rule) {
            //validation extand
            \Validator::extend('greater_than', function($attribute, $value, $parameters)
            {
                return $value >= $parameters[0];
            });

            if(empty($contract_rule['min_amount'])){
                $minAmount = 0;
            }else{
                $minAmount = $contract_rule['min_amount'];
            }

            $rules += [
                'rule.'.$rule_id.'.type'          =>'required',
                'rule.'.$rule_id.'.type_amount'   =>'required|numeric',
                'rule.'.$rule_id.'.base'          =>'required',
                'rule.'.$rule_id.'.operand'       =>'required',
                'rule.'.$rule_id.'.min_amount'    =>'sometimes|numeric',
                'rule.'.$rule_id.'.max_amount'    =>'sometimes:operand,Between|numeric|greater_than:'.$minAmount,
                'rule.'.$rule_id.'.fixed_charge'  =>'required|boolean',
            ];

            if (isset($contract_rule['categories'])) {
                foreach ($contract_rule['categories'] as $category) {
                    $rules['rule.'.$rule_id.'.categories'] = 'required|exists:categories,id';
                }
            }
            if (isset($contract_rule['channels'])) {
               foreach ($contract_rule['channels'] as $channel) {
                    $rules['rule.'.$rule_id.'.channels'] = 'required|exists:channels,id';
                }
            }
            if (isset($contract_rule['products'])) {
               foreach ($contract_rule['products'] as $product) {
                    $rules['rule.'.$rule_id.'.products'] = 'required|exists:products,id';
                }
            }
        }

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

        if(!isset($data['contract']['guarantee'])){
            $data['contract']['guarantee'] = NULL;
        }

        $existingContracts = Contract::where('brand_id', '=', $data['contract']['brand_id'])->where('merchant_id', '=', $data['contract']['merchant_id'])->where('id', '!=', $id)->get();

        $return = array();

        if($existingContracts->count() > 0){
            // check if there is conflicting date
            $startDate = $data['contract']['start_date'];
            $endDate = (!empty($data['contract']['end_date']))?$data['contract']['end_date']:false;

            $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
            $startDate->hour = 00;
            $startDate->minute = 00;
            $startDate->second = 00;

            if($endDate){
                $endDate = Carbon::createFromFormat('Y-m-d', $endDate);
                $endDate->hour = 23;
                $endDate->minute = 59;
                $endDate->second = 59;
            }

            $duplicateContracts = array();

            foreach ($existingContracts as $contract) {
                if(!empty($contract->start_date)){
                    $contractStartDate = Carbon::createFromFormat('Y-m-d', $contract->start_date);
                    if(is_null($contract->end_date)){
                        // no end date is set, check if start date is after contract's start date
                        if($startDate->gte($contractStartDate)
                            || ($endDate && $endDate->gte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractStartDate))){
                            // compare start date
                            $duplicateContracts[] = $contract->id;
                        }
                    }else{
                        $contractEndDate = Carbon::createFromFormat('Y-m-d', $contract->end_date);
                        // check if start and end date is in between
                        if($startDate->between($contractStartDate, $contractEndDate)
                            || ($endDate && $endDate->between($contractStartDate, $contractEndDate))
                            || ($endDate && $contractStartDate->between($startDate, $endDate))
                            || ($endDate && $contractEndDate->between($startDate, $endDate))
                            || (!$endDate && $startDate->lte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractEndDate))){
                            $duplicateContracts[] = $contract->id;
                        }
                    }
                }
            }

            if(count($duplicateContracts) > 0){
                $return['duplicate'] = $this->whereIn('id', $duplicateContracts);
                $return['error'] = true;

                return $return;
            }
        }

        #update contract...
        // $contract = $this->model->find($id);
        $update = $this->model->where('id', '=', $id)->update($data['contract']);

        #get the exist rule id...
        $getRules = ContractRule::where('contract_id', '=', $id)->get(['id']);
        foreach ($getRules as $getId) {
            $idArray[$getId->id] = $getId->id;
        }
        #update rule...
        foreach ($data['rule'] as $rule_id => $contract_rule) {
            if (isset($contract_rule['categories'])) {
               $contract_rule['categories'] = json_encode($contract_rule['categories']);
            }
            if (isset($contract_rule['channels'])) {
               $contract_rule['channels'] = json_encode($contract_rule['channels']);
            }
            if (isset($contract_rule['products'])) {
               $contract_rule['products'] = json_encode($contract_rule['products']);
            }
            $contract_rule['contract_id'] = $id;

            #remove the rule id done update
            if (isset($contract_rule['id'])) {
                $idArray = array_diff_key($idArray, [$contract_rule['id'] => $contract_rule['id']]);
            }

            if (is_null($this->rule->find($contract_rule['id']))) {
                $createRule = $this->rule->create($contract_rule);
            }elseif (!is_null($this->rule->find($contract_rule['id']))) {
                $update_rule = $this->rule->where('id', '=', $contract_rule['id'])->update($contract_rule);
            }
        }

        #delete rule...
        if (is_array($idArray)) {
            foreach ($idArray as $value) {
                $this->rule->where('id', '=', $value)->delete();
            }
        }

        Activity::log('Contract '.$id.' was updated', $this->user_id);

        $return['contract_index'] = $this->with('brand', 'merchant', 'contract_rules')->find($id);
        return $return;
    }

    public function duplicate($id)
    {
        $data = Contract::where('id', $id)->with('contract_rules')->first();

        $get['name']            = $data['name'];
        $get['brand_id']        = $data['brand_id'];
        $get['merchant_id']     = $data['merchant_id'];
        $get['guarantee']       = $data['guarantee'];
        $get['min_guarantee']   = $data['min_guarantee'];

        #update contract...
        $create = $this->model->create($get);

        foreach ($data->contract_rules as $rules) {
            $rule['contract_id']    = $create->id;
            $rule['type']           = $rules['type'];
            $rule['type_amount']    = $rules['type_amount'];
            $rule['base']           = $rules['base'];
            $rule['operand']        = $rules['operand'];
            $rule['min_amount']     = $rules['min_amount'];
            $rule['max_amount']     = $rules['max_amount'];
            $rule['fixed_charge']   = $rules['fixed_charge'];

            if (isset($rules['categories'])) {
               $rule['categories'] = $rules['categories'];
            }
            if (isset($rules['channels'])) {
               $rule['channels'] = $rules['channels'];
            }
            if (isset($rules['products'])) {
               $rule['products'] = $rules['products'];
            }
            $createRule = $this->rule->create($rule);
        }

        Activity::log('Contract '.$create->id.' was duplicated base on '.$id, $this->user_id);

        $return['contract_index'] = $this->with('brand', 'merchant', 'contract_rules')->find($create->id);
        $return['duplicate'] = true;
        return $return;
    }

    public function delete($id)
    {
        $contract  = $this->findOrFail($id);
        $ack  = parent::delete($id);
        if ($ack == 1) {
            $this->rule->where('contract_id', '=', $id)->delete();
            Activity::log('Contract ('.$id.')  was deleted', $this->user_id);
        }
        return $ack;
    }

    public function updateDate(array $data, $id)
    {
        $rules = [
            'start_date'    => 'required|date_format:Y-m-d',
            'end_date'      => 'sometimes|date_format:Y-m-d|after:start_date',
        ];

        $v = \Validator::make($data, $rules);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $currContract = $this->find($id);

        $existingContracts = Contract::where('brand_id', '=', $currContract->brand_id)->where('merchant_id', '=', $currContract->merchant_id)->where('id', '!=', $id)->get();

        $return = array();

        if($existingContracts->count() > 0){
            // check if there is conflicting date
            $startDate = $data['start_date'];
            $endDate = (!empty($data['end_date']))?$data['end_date']:false;

            $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
            $startDate->hour = 00;
            $startDate->minute = 00;
            $startDate->second = 00;

            if($endDate){
                $endDate = Carbon::createFromFormat('Y-m-d', $endDate);
                $endDate->hour = 23;
                $endDate->minute = 59;
                $endDate->second = 59;
            }

            $duplicateContracts = array();

            foreach ($existingContracts as $contract) {
                if(!empty($contract->start_date)){
                    $contractStartDate = Carbon::createFromFormat('Y-m-d', $contract->start_date);
                    if(is_null($contract->end_date)){
                        // no end date is set, check if start date is after contract's start date
                        if($startDate->gte($contractStartDate)
                            || ($endDate && $endDate->gte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractStartDate))){
                            // compare start date
                            $duplicateContracts[] = $contract->id;
                        }
                    }else{
                        $contractEndDate = Carbon::createFromFormat('Y-m-d', $contract->end_date);
                        // check if start and end date is in between
                        if($startDate->between($contractStartDate, $contractEndDate)
                            || ($endDate && $endDate->between($contractStartDate, $contractEndDate))
                            || ($endDate && $contractStartDate->between($startDate, $endDate))
                            || ($endDate && $contractEndDate->between($startDate, $endDate))
                            || (!$endDate && $startDate->lte($contractStartDate))
                            || (!$endDate && $startDate->lte($contractEndDate))){
                            $duplicateContracts[] = $contract->id;
                        }
                    }
                }
            }

            if(count($duplicateContracts) > 0){
                $return['duplicate'] = $duplicateContracts;
                $return['error'] = true;

                return $return;
            }
        }

        $update = $currContract->update($data);
        Activity::log('Contract '.$id.' was updated', $this->user_id);
        $return['success'] = true;

        return $return;
    }

    private static function inventoryQuery($merchant_id, $brand_id, $startDateTime, $endDateTime, $timezone ="+08:00") {

        // $startDateTime = Carbon::createFromFormat('Y-m-d',$startDateTime)->setTimezone('UTC')->format('Y-m-d H:i:s');
        // $endDateTime = Carbon::createFromFormat('Y-m-d',$endDateTime)->setTimezone('UTC')->format('Y-m-d H:i:s');
            
        $response = array();

        $query = 'SELECT IF (brands.`name` IS NOT NULL , brands.`name` , products.`brand`) AS Brand, 
                products.`brand_id`,
                sku.`hubwire_sku` AS "Hubwire SKU",
                sku.`sku_supplier_code` AS "Merchant SKU",products.`name` AS "Product Name",
                MAX(size.`option_value`) AS Size,
                MAX(colour.`option_value`) AS Color,
                IF(available_stock_start.`quantity` IS NOT NULL, available_stock_start.`quantity`, 0)+
                IF(reserved_start.`quantity` IS NOT NULL, reserved_start.`quantity`, 0)+
                IF(unreceived_st_start.`quantity` IS NOT NULL, unreceived_st_start.`quantity`, 0) +
                IF(correction.`quantity` IS NOT NULL, correction.`quantity`, 0) AS "Stock (Start)",
                IF(purchases.`inbound` IS NOT NULL, purchases.`inbound`, 0) AS "Inbound",
                IF(reject_items.`outbound` IS NOT NULL, reject_items.`outbound`, 0) AS "Outbound",
                IF(completed_orders.sold IS NOT NULL, completed_orders.`sold`, 0) as "Sold",
                IF(return_processed.return_qty IS NOT NULL, return_processed.return_qty, 0) AS "Returns",
                IF(available_stock_end.`quantity` IS NOT NULL, available_stock_end.`quantity`, 0) +
                IF(reserved_end.`quantity` IS NOT NULL, reserved_end.`quantity`, 0) +
                IF(unreceived_st_end.`quantity` IS NOT NULL, unreceived_st_end.`quantity`, 0) AS "Stock (End)"
                FROM sku
                LEFT JOIN sku_combinations ON sku_combinations.sku_id = sku.sku_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Colour") as colour ON sku_combinations.option_id = colour.option_id
                LEFT JOIN (SELECT * FROM sku_options WHERE sku_options.option_name = "Size") as size ON sku_combinations.option_id = size.option_id
                LEFT JOIN products ON sku.product_id = products.id
                LEFT JOIN brands ON products.brand_id = brands.id
                LEFT JOIN merchants ON brands.merchant_id = merchants.id
                LEFT JOIN (
                    SELECT purchase_items.sku_id, min(purchase_batches.receive_date) AS receive_date
                    FROM purchase_batches
                    LEFT JOIN purchase_items ON purchase_batches.batch_id = purchase_items.batch_id
                    WHERE purchase_batches.receive_date < "'.$endDateTime.'"
                    AND purchase_batches.batch_status = 1
                    GROUP BY purchase_items.sku_id
                ) product_procured ON sku.sku_id = product_procured.sku_id
                LEFT JOIN (
                    SELECT MAX(id) as id,sku_id
                    FROM sku_quantity_log
                    WHERE sku_quantity_log.created_at <= "'.$startDateTime.'"
                    GROUP BY sku_id
                ) stock_start_ids ON stock_start_ids.sku_id = sku.sku_id
                LEFT JOIN sku_quantity_log available_stock_start on stock_start_ids.id = available_stock_start.id
                LEFT JOIN (
                    SELECT sku_id , sum(quantity_new) as quantity from
                    (SELECT channel_sku_id, MAX(id) as id
                    FROM reserved_quantities_log
                    WHERE reserved_quantities_log.created_at <= "'.$startDateTime.'"
                    GROUP BY channel_sku_id) reserved_quantity_ids
                    LEFT JOIN reserved_quantities_log on reserved_quantity_ids.id = reserved_quantities_log.id
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = reserved_quantities_log.channel_sku_id
                    GROUP BY channel_sku.sku_id
                )reserved_start ON reserved_start.sku_id = sku.sku_id
                LEFT JOIN (
                SELECT
                    di.sku_id , sum(di.quantity) as quantity
                FROM
                    delivery_orders_items di
                LEFT JOIN delivery_orders d_o ON di.do_id = d_o.id
                WHERE
                    di.deleted_at IS NULL
                        AND (d_o.deleted_at IS NULL OR d_o.deleted_at >= "'.$startDateTime.'")
                        AND (d_o.receive_at IS NULL OR d_o.receive_at >= "'.$startDateTime.'")
                        AND d_o.sent_at < "'.$startDateTime.'"
                        GROUP BY di.sku_id
                ) unreceived_st_start on unreceived_st_start.sku_id = sku.sku_id
                LEFT JOIN (
                    SELECT purchase_items.sku_id, SUM(purchase_items.item_quantity) AS inbound, purchase_batches.batch_id
                    FROM purchase_batches
                    LEFT JOIN purchase_items ON purchase_batches.batch_id = purchase_items.batch_id
                    WHERE purchase_batches.receive_date >= "'.$startDateTime.'" AND purchase_batches.receive_date < "'.$endDateTime.'"
                    AND purchase_batches.batch_status = 1
                    GROUP BY purchase_items.sku_id
                ) purchases ON sku.sku_id = purchases.sku_id
                LEFT JOIN (
                    SELECT channel_sku.sku_id, sum(return_log.quantity) AS return_qty
                    FROM return_log
                    LEFT JOIN order_items on order_items.id  = return_log.order_item_id and order_items.ref_type = "ChannelSKU"
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = order_items.ref_id
                    WHERE return_log.completed_at >= "'.$startDateTime.'"
                    AND return_log.completed_at < "'.$endDateTime.'"
                    AND return_log.quantity <> 0
                    AND return_log.order_item_id <> 0
                    AND order_items.status <> "Cancelled"
                    GROUP BY channel_sku.sku_id
                ) return_processed ON sku.sku_id = return_processed.sku_id
                 LEFT JOIN (
                    SELECT channel_sku.sku_id, SUM(order_items.original_quantity) AS sold
                    FROM order_items
                    LEFT JOIN orders ON orders.id = order_items.order_id
                    LEFT JOIN channel_sku ON channel_sku.channel_sku_id = order_items.ref_id
                    LEFT JOIN order_status_log ON order_status_log.to_status = "Completed" and order_status_log.order_id = orders.id
                    WHERE order_status_log.created_at >= "'.$startDateTime.'"
                    AND order_status_log.created_at <= "'.$endDateTime.'"
                    AND orders.status >= 32
                    AND order_items.status in ("Verified","Returned")
                    AND order_status_log.user_id = 0
                    GROUP BY channel_sku.sku_id
                ) completed_orders ON sku.sku_id = completed_orders.sku_id
                LEFT JOIN (
                    SELECT reject_log.sku_id, SUM(reject_log.quantity) AS outbound
                    FROM reject_log
                    WHERE reject_log.created_at >= "'.$startDateTime.'" AND reject_log.created_at < "'.$endDateTime.'"
                    AND outbound = 1 GROUP BY reject_log.sku_id
                ) reject_items ON sku.sku_id = reject_items.sku_id
                LEFT JOIN(
                    SELECT MAX(id) as id,sku_id
                    FROM sku_quantity_log
                    WHERE sku_quantity_log.created_at <= "'.$endDateTime.'"
                    GROUP BY sku_id
                ) stock_end_ids ON stock_end_ids.sku_id = sku.sku_id
                LEFT JOIN sku_quantity_log available_stock_end on stock_end_ids.id = available_stock_end.id
                LEFT JOIN (
                    SELECT sku_id , sum(quantity_new) as quantity from
                    (SELECT channel_sku_id, MAX(id) as id
                    FROM reserved_quantities_log
                    WHERE reserved_quantities_log.created_at <= "'.$endDateTime.'"
                    GROUP BY channel_sku_id) reserved_quantity_ids
                    LEFT JOIN reserved_quantities_log on reserved_quantity_ids.id = reserved_quantities_log.id
                    LEFT JOIN channel_sku on channel_sku.channel_sku_id = reserved_quantities_log.channel_sku_id
                    GROUP BY channel_sku.sku_id
                )reserved_end ON reserved_end.sku_id = sku.sku_id
                LEFT JOIN (
                SELECT
                di.sku_id , sum(di.quantity) as quantity
                FROM
                    delivery_orders_items di
                LEFT JOIN delivery_orders d_o ON di.do_id = d_o.id
                WHERE
                    di.deleted_at IS NULL
                        AND (d_o.deleted_at IS NULL OR d_o.deleted_at >= "'.$endDateTime.'")
                        AND (d_o.receive_at IS NULL OR d_o.receive_at >= "'.$endDateTime.'")
                        AND d_o.sent_at < "'.$endDateTime.'"
                        GROUP BY di.sku_id
                ) unreceived_st_end on unreceived_st_end.sku_id = sku.sku_id
                LEFT JOIN 
                (
                    select sku_id , sum(quantity) as quantity from stock_correction where corrected_at between "'.$startDateTime.'" and "'.$endDateTime.'" group by sku_id
                )correction on correction.sku_id = sku.sku_id  WHERE products.merchant_id = "'.$merchant_id.'" AND products.brand_id = "'.$brand_id.'"
            AND (products.deleted_at IS NULL OR products.deleted_at > "'.$endDateTime.'")
            AND (sku.deleted_at IS NULL OR sku.deleted_at > "'.$endDateTime.'")
            AND (brands.deleted_at IS NULL OR brands.deleted_at > "'.$endDateTime.'")
            AND sku.`created_at` < "'.$endDateTime.'" GROUP BY sku.sku_id
                    ORDER BY products.created_at ASC, hubwire_sku ASC';
        $result = DB::select(DB::raw($query));
        $inventory = collect($result);
        
        return $inventory;
    }

    private static function storageQuery($merchant_id, $brand_id, $startdate, $enddate, $timezone ="+08:00"){
        $query = "SELECT p.name,sku.sku_id,sku.hubwire_sku, p.merchant_id, IFNULL( b.max_quantity,IFNULL(ql.quantity,0)) as quantity,p.brand_id FROM sku
                    LEFT JOIN (
                        SELECT MAX(id) log_id,sku_id FROM sku_quantity_log
                        GROUP BY sku_id 
                    ) a ON a.sku_id = sku.sku_id
                    LEFT JOIN sku_quantity_log ql ON ql.id = a.log_id
                    LEFT JOIN (
                        SELECT MAX(quantity) AS max_quantity,sku_id FROM sku_quantity_log
                        WHERE created_at BETWEEN '".$startdate."' AND '".$enddate."'
                        GROUP BY sku_id
                    ) b ON b.sku_id = sku.sku_id
                    LEFT JOIN products p ON p.id = sku.product_id
                    WHERE p.merchant_id = '".$merchant_id."' AND p.brand_id ='".$brand_id."'";
        
        $storages = collect(\DB::select(DB::raw($query)));
        return $storages;
    }

    public static function calculator($data) 
    {
        $orders = Order::with('chargeableItems')
                            ->where('status', '=', Order::$completedStatus)
                            ->whereBetween('shipped_date', $data['dateTimeRange'])
                            ->get();

        if ($data['contractClass'] == 'ChannelContract') {
            $contract = ChannelContract::find($data['contractId']);
        }else{
            $contract = Contract::find($data['contractId']);

            $inventory  = self::inventoryQuery($contract->merchant_id, $contract->brand_id, $data['dateTimeRange'][0], $data['dateTimeRange'][1]);
            //Inbound
            $inbound = $inventory->sum('Inbound');
            $inbound_fee = $inbound*$contract->inbound_fee;

            //Outbound
            $outbound = $inventory->sum('Outbound');
            $outbound_fee = $outbound*$contract->outbound_fee;

            //Storage
            $storages = self::storageQuery($contract->merchant_id, $contract->brand_id, $data['dateTimeRange'][0], $data['dateTimeRange'][1]);
            $storage = $storages->sum('quantity');
            $storage_fee = $storage * $contract->storage_fee;
        }

        

        $numberOfOrder = array();
        $return['totalOrder'] = 0;
        $return['totalItem'] = 0;
        $return['totalSales'] = 0;
        $return['totalListing'] = 0;
        $return['totalRetails'] = 0;
        $return['fee']['type'] = '';
        $return['fee']['amount'] = 0;
        $return['fee']['channel'] = array();
        $return['fee']['channel'][$contract->channel_id]['fee'] = 0;
        $return['fee']['channel'][$contract->channel_id]['mg'] = 0;
        $return['itemId'] = array();
        $total_shipped = 0;
        $total_return = 0;
        foreach ($orders as $order) {
            $numberOfOrder[$order->id] = 0;
            foreach ($order->chargeableItems as $item) {

                if ($item->merchant_id == $contract->merchant_id && $item->ref->product->brand_id == $contract->brand_id) {
                    $orderItemRepo = new OrderItemRepository($item, true);
                    $orderItemRepo->setDateTimeRange($data['dateTimeRange']);
                    $orderItemRepo->setContractClass($data['contractClass']);
                    $orderItemRepo->getItemBrandContract($data['contractId']);
                    $response = $orderItemRepo->calculateFee($updateFees = false);
                    if ($response['success']) {
                        if(strcasecmp($item->status, 'Returned') == 0)
                            $total_return++;
                        else 
                            $total_shipped++;
                        
                        $numberOfOrder[$response['order']]++;
                        $return['totalItem']++;
                        $return['totalSales'] += $response['sale'];
                        $return['totalListing'] += $response['listing'];
                        $return['totalRetails'] += $response['retail'];
                        $return['fee']['type'] = $response['type'];
                        $return['fee']['amount'] += $response['fee'];
                        //$return['fee']['amount'] += $response['mg'];
                        if ($response['channel']) {
                            $return['fee']['channelName'] = $response['channelName'];
                            //$return['fee']['channel'][$response['channelId']]['id'] = $response['channelId'];
                            //$return['fee']['channel'][$response['channelId']]['name'] = $response['channelName'];
                            //$return['fee']['channel'][$response['channelId']]['fee'] += $response['fee'];
                            //$return['fee']['channel'][$response['channelId']]['mg'] += $response['mg'];
                        }
                        $return['itemId'][$response['item']]['id'] = $response['item'];
                        $return['itemId'][$response['item']]['fee'] = $response['fee'];
                        $return['itemId'][$response['item']]['mg'] = $response['mg'];
                    }
                }
            }
        }
        foreach ($numberOfOrder as $orderId => $value) {
            if ($value>0) {
                $return['totalOrder']++;
            }
        }

        if($data['contractClass'] !== 'ChannelContract'){

            $return['inbound'] = $inbound;
            $return['inbound_fee'] = $inbound_fee;

            $return['outbound'] = $outbound;
            $return['outbound_fee'] = $outbound_fee;

            $return['storage'] = $storage;
            $return['storage_fee'] = $storage_fee;

            $return['return'] = $total_return;
            $return['return_fee'] = $total_return*$contract->return_fee;

            $return['shipped'] = $total_shipped;
            $return['shipped_fee'] = $total_shipped*$contract->shipped_fee;

        }

        return $return;
    }

    public function calculateFee($request)
    {
        if ($request['contract_type'] == 'Hubwire Fee') {
            $data['contractClass'] = 'Contract';
        }elseif ($request['contract_type'] == 'Channel Fee') {
            $data['contractClass'] = 'ChannelContract';
        }
        
        $startOfMonth = Carbon::createFromFormat('M-Y', $request['month'])->startOfMonth()->toDateString();
        $endOfMonth = Carbon::createFromFormat('M-Y', $request['month'])->endOfMonth()->toDateString();
        $data['dateTimeRange'] = [$startOfMonth, $endOfMonth];
        $data['contractId'] = $request['contract'];
        $response = $this->calculator($data);

        return $response;
    }

    public function exportFeeReport($request)
    {
        $itemArr = array();
        $test_merchant = "Test Merchant";
        $gst = config('globals.GST');
        $masterData = array();

        $threeMonthsBefore = Carbon::createFromFormat('Y-m-d', $request['endDate'])->subMonths(3);

        $merchants = Merchant::select('id', 'name', 'slug')
                        ->where('status', '=', 'Active')
                        ->orWhere(function ($query) use ($threeMonthsBefore) {
                                    $query->where('status', '=', 'Inactive')
                                        ->where('deactivated_date', '>', $threeMonthsBefore);
                                })
                        ->get()
                        ->keyBy('id');
        $merchants->prepend(array('id' => 0, 'name' => 'Deactivated', 'slug' => 'Deactivated Merchants'), 0);
        $merchants = $merchants->toArray();

        foreach ($request['items'] as $itemId => $itemFee) {
            $item = OrderItem::findOrFail($itemId);
            if ($item->isChargeable()) {        
                if($item->tax_inclusive == true) {
                    $soldAmount = $item->sold_price;
                    $soldAmountWithoutGst = $item->sold_price - $item->tax;
                } else if($item->tax_inclusive == false) {
                    $soldAmount = $item->sold_price + $item->tax;
                    $soldAmountWithoutGst = $item->sold_price;
                }
                $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                $channel_sku = ChannelSKU::find($item->ref_id);
                $itemArr[] = array(
                    'sku_id'                        => $item->ref->sku->sku_id,
                    'hw_fee'                        => $item->hw_fee,
                    'min_guarantee'                 => $item->min_guarantee,
                    'channel_fee'                   => $item->channel_fee,
                    'channel_mg'                    => $item->channel_mg,
                    'channel_id'                    => $channel_sku->channel_id,
                    'unit_price'                    => $item->unit_price,
                    'sale_price'                    => ($item->sale_price == 0)?$item->unit_price:$item->sale_price,
                    'total_amount_paid'             => $soldAmount * $item->original_quantity,
                    'total_amount_paid_excl_gst'    => $soldAmountWithoutGst * $item->original_quantity,
                    'total_discount'                => $discount * $item->original_quantity,
                    'total_quantity'                => $item->original_quantity,
                    'id'                            => $item->order->id,
                    'orderCompletedDate'            => !($item->order->orderDate($item->order->id)) ? $item->order->orderDate($item->order->id) : Carbon::createFromFormat('Y-m-d H:i:s', $item->order->orderDate($item->order->id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'tp_order_date'                 => $item->order->tp_order_date,
                    'tp_order_code'                 => $item->order->tp_order_code,
                    'currency'                      => $item->order->currency,
                    'consignment_no'                => $item->order->consignment_no,
                );
                if ($request['type'] == 'hubwireFee') {
                    $hubwireFee = number_format($itemFee['fee'] / $gst, 3);
                    $hubwireMg = number_format($itemFee['mg'] / $gst, 3);
                }else{
                    $channelFee = number_format($itemFee['fee'] / $gst, 3);
                    $channelMg = number_format($itemFee['mg'] / $gst, 3);
                }
            }
        }

        foreach ($itemArr as $itemTotals) {
            $sku = SKU::find($itemTotals['sku_id']);
            $product = Product::with('brands', 'merchant', 'category')->find($sku->product_id);
            $channel = Channel::with('channel_type')->find($itemTotals['channel_id']);
            $return['fileNameDetails']['merchantSlug'] = $product->merchant->slug;
            $return['fileNameDetails']['brandName'] = $product->brands->name;

            if(strcmp($product->merchant->name, $test_merchant)==0){
                continue;
            }

            $get_issuing_companies = json_decode(json_encode(DB::table('issuing_companies')->where('id', $channel->issuing_company)->first()), true);
            $gst_reg = $get_issuing_companies['gst_reg'];
            $category = '';

            if (isset($product->category->full_name) && !empty($product->category->full_name)) {
                $category = explode('/', $product->category->full_name);
            }

            $reportData = [
                'Merchant'                          => $product->merchant->name,
                'Channel'                           => $channel->name,
                'Channel Type'                      => $channel->channel_type->name,
                'Third Party Order Date'            => (!is_null($itemTotals['tp_order_date'])) ? Carbon::createFromFormat('Y-m-d H:i:s', $itemTotals['tp_order_date'])->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s') : '',
                'Order Completed Date'              => $itemTotals['orderCompletedDate'],
                'Order No'                          => $itemTotals['id'],
                'Third Party Order No'              => $itemTotals['tp_order_code'],
                'Brand'                             => $product->getRelation('brands')->name,
                'Hubwire SKU'                       => $sku->hubwire_sku,
                'Supplier SKU'                      => $sku->sku_supplier_code,
                'Product Name'                      => $product->name,
                'Main Category'                     => isset($category[0]) ? $category[0] : '',
                'Sub-category'                      => isset($category[1]) ? $category[1] : '',
                'Sub-subcategory'                   => isset($category[2]) ? $category[2] : '',
                'Size'                              => $sku->size,
                'Color'                             => $sku->color,
                'Quantity'                          => $itemTotals['total_quantity'],
                'Currency'                          => $itemTotals['currency'],
                'Retail Price (Incl. GST)'          => ($gst_reg == 1) ? number_format($itemTotals['unit_price'], 2) : number_format($itemTotals['unit_price']/$gst, 2),
                'Retail Price (Excl. GST)'          => number_format($itemTotals['unit_price']/$gst, 2),
                'Listing Price (Incl. GST)'         => ($gst_reg == 1) ? number_format($itemTotals['sale_price'], 2) : number_format($itemTotals['sale_price']/$gst, 2),
                'Listing Price (Excl. GST)'         => number_format($itemTotals['sale_price']/$gst, 2),
                'Discounts'                         => number_format($itemTotals['total_discount'], 2), // sum of all the quantities
                'Total Sales (Incl. GST)'           => ($gst_reg == 1) ? number_format($itemTotals['total_amount_paid'], 2) : number_format($itemTotals['total_amount_paid'], 2),
                'Total Sales (Excl. GST)'           => ($gst_reg == 1) ? number_format($itemTotals['total_amount_paid_excl_gst'], 2) : number_format($itemTotals['total_amount_paid'], 2),
            ];

            if ($request['type'] == 'hubwireFee') {
                $reportData['FM Hubwire Fee (Excl. GST)']           = $hubwireFee;
                $reportData['Minimum Guarantee (Excl. GST)']        = $hubwireMg;
            }else{
                $reportData['Channel Fee (Excl. GST)']              = $channelFee;
                $reportData['Channel Min Guarantee (Excl. GST)']    = $channelMg;
            }

            $reportData['Consignment Number'] = $itemTotals['consignment_no'];

            // if merchant/brand is active or has been deactivated for less than three months
            if ((array_key_exists($product->merchant_id, $merchants) || $product->merchant->deactivated_date > $threeMonthsBefore) &&
                ($product->getRelation('brands')->active || $product->getRelation('brands')->deactivated_date > $threeMonthsBefore)) {
                $masterData[] = $reportData;
            }
        }  
        $return['mainData'] = json_encode($masterData);
        $return['fileNameDetails']['ym'] =  Carbon::createFromFormat('Y-m-d', $request['endDate'])->format('ym');

        return $return;
    }
}
