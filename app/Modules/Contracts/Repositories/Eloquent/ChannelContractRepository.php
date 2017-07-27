<?php namespace App\Modules\Contracts\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\ChannelContract;
use App\Models\Admin\ChannelContractRule;
use App\Models\Admin\Channel;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class ChannelContractRepository extends Repository
{
    protected $model;

    protected $rule;

    protected $role;

    protected $skipCriteria = true;

    protected $user_id;


    public function __construct(ChannelContract $model, ChannelContractRule $rule)
    {
        $this->model = $model;
        $this->rule = $rule;
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\ChannelContract';
    }

    public function store(array $data)
    {
        // Inputs validations
        $rules = [
            'contract.name'          => 'required',
            'contract.channel_id'    => 'required|integer|exists:channels,id',
            'contract.brand_id'      => 'required|integer|exists:brands,id',
            'contract.merchant_id'   => 'required|integer|exists:merchants,id',
            'contract.guarantee'     => 'sometimes|numeric',
            'contract.start_date'    => 'required|date_format:Y-m-d',
            'contract.end_date'      => 'sometimes|date_format:Y-m-d|after:contract.start_date',
            'contract.min_guarantee' => 'required|boolean',
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
        $existingContracts = ChannelContract::where('brand_id', '=', $data['contract']['brand_id'])->where('merchant_id', '=', $data['contract']['merchant_id'])->get();
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
            if (isset($contract_rule['products'])) {
               $contract_rule['products'] = json_encode($contract_rule['products']);
            }
            $contract_rule['contract_id'] = $create->id;
            $createRule = $this->rule->create($contract_rule);
        }
        
        Activity::log('Channel contract '.$create->id.' was created', $this->user_id);
        
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
        
        $existingContracts = ChannelContract::where('channel_id', '=', $data['contract']['channel_id'])->where('brand_id', '=', $data['contract']['brand_id'])->where('merchant_id', '=', $data['contract']['merchant_id'])->where('id', '!=', $id)->get();
        
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
        $getRules = ChannelContractRule::where('contract_id', '=', $id)->get(['id']);
        foreach ($getRules as $getId) {
            $idArray[$getId->id] = $getId->id;
        }
        #update rule...
        foreach ($data['rule'] as $rule_id => $contract_rule) {
            if (isset($contract_rule['categories'])) {
               $contract_rule['categories'] = json_encode($contract_rule['categories']);
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

        Activity::log('Channel contract '.$id.' was updated', $this->user_id);

        $return['contract_index'] = $this->with('channel', 'brand', 'merchant', 'channel_contract_rules')->find($id);
        return $return;
    }

    public function duplicate($id)
    {
        $data = ChannelContract::where('id', $id)->with('channel_contract_rules')->first();
        
        $get['name']            = $data['name'];
        $get['channel_id']      = $data['channel_id'];
        $get['brand_id']        = $data['brand_id'];
        $get['merchant_id']     = $data['merchant_id'];
        $get['guarantee']       = $data['guarantee'];
        $get['min_guarantee']   = $data['min_guarantee'];

        #update contract...
        $create = $this->model->create($get);

        foreach ($data->channel_contract_rules as $rules) {
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
            if (isset($rules['products'])) {
               $rule['products'] = $rules['products'];
            }
            $createRule = $this->rule->create($rule);
        }

        Activity::log('Channel contract '.$create->id.' was duplicated base on '.$id, $this->user_id);

        $return['contract_index'] = $this->with('channel', 'brand', 'merchant', 'channel_contract_rules')->find($create->id);
        $return['duplicate'] = true;
        return $return;
    }

    public function delete($id)
    {
        $contract  = $this->findOrFail($id);
        $ack  = parent::delete($id);
        if ($ack == 1) {
            $this->rule->where('contract_id', '=', $id)->delete();
            Activity::log('Channel contract ('.$id.')  was deleted', $this->user_id);
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

        $existingContracts = ChannelContract::where('channel_id', '=', $currContract->channel_id)->where('brand_id', '=', $currContract->brand_id)->where('merchant_id', '=', $currContract->merchant_id)->where('id', '!=', $id)->get();

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
        Activity::log('Channel contract '.$id.' was updated', $this->user_id);
        $return['success'] = true;

        return $return;
    }
}
