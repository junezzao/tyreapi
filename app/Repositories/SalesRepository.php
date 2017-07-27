<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;

class SalesRepository extends Repository
{
    protected $maps = [
            'customer_id' => 'member_id',
            'status' => 'sale_status',
            'total_price' => 'sale_total',
            'total_discount' => 'sale_discount',
            'shipping_fee' => 'sale_shipping',
            'shipping_info.recipient' => 'sale_recipient',
            'shipping_info.address_1' => 'sale_street_1',
            'shipping_info.address_2' => 'sale_street_2',
            'shipping_info.postcode' => 'sale_postcode',
            'shipping_info.city' => 'sale_city',
            'shipping_info.state' => 'sale_state',
            'shipping_info.country' => 'sale_country',
            'shipping_info.phone' => 'sale_phone',
            'shipping_info.tracking_no' => 'consignment_no',
            'order_number' => 'order_code',
            'reference_id' => 'ref_id'
    ];
    public function model()
    {
        return 'App\Models\Admin\Sales';
    }

    public function fulfillment()
    {
    }

    public function create(array $inputs)
    {
        // Inputs validations

        $v = \Validator::make($inputs, [
            'client_id' => 'required|exists:clients',
            'channel_id' => 'required|exists:channels,channel_id,client_id,'.$inputs['client_id'],
            'customer_id' => 'sometimes|required|exists:members,member_id',
            'status' => 'required|in:paid',
            'payment_type' => 'required|string',
            'total_price' => 'required|numeric|min:0',
            'total_discount' => 'sometimes|required|numeric|min:0',
            'shipping_fee' => 'required|numeric|min:0',
            'customer' => 'required_without:customer_id|array',
            'items' => 'required|array',
            'shipping_info.recipient' => 'required|string',
            'shipping_info.address_1' => 'required|string',
            'shipping_info.address_1' => 'required|string',
            'shipping_info.postcode' => 'required|string',
            'shipping_info.city' => 'required|string',
            'shipping_info.state' => 'required|string',
            'shipping_info.country' => 'required|string',
            'shipping_info.phone' => 'required|string',
            'shipping_info.tracking_no' => 'required|string',
            'reference_id' => 'sometimes|integer|min:1|unique:sales,ref_id,NULL,sale_id,channel_id,'.$inputs['channel_id'],
            'order_number' => 'sometimes|required|string|unique:sales,order_code,NULL,sale_id,channel_id,'.$inputs['channel_id'],
            'order_date' => 'required|date_format:Y-m-d H:i:s',
            'notification_date' => 'required_if:sale_status,shipped',
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($inputs as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        foreach ($inputs['shipping_info'] as $k => $v) {
            $key = $k;
            if (isset($this->maps['shipping_info.'.$key])) {
                $key = $this->maps['shipping_info.'.$key];
            }
            $newinputs[$key] = $v;
        }
        $newinputs['extra'] = serialize([ 'created_at' => $inputs['order_date'] ]);
        $inputs = $newinputs;
        unset($inputs['access_token']);
        unset($inputs['items']);
        unset($inputs['customer']);
        unset($inputs['shipping_info']);
        // \Log::info(print_r($inputs, true));
        $sale = parent::create($inputs);
        return $this->find($sale->sale_id);
    }

    
    public function update(array $data, $id, $attribute="sale_id")
    {
        // Inputs validations

        $v = \Validator::make($data, [
            'status' => 'required|in:paid',
            'payment_type' => 'required|string',
            'total' => 'required|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'customer' => 'required_without:customer_id|array',
            'items' => 'required|array',
            'shipping_info.recipient' => 'required|string',
            'shipping_info.address' => 'required|string',
            'shipping_info.postcode' => 'required|string',
            'shipping_info.country' => 'required|string',
            'shipping_info.phone' => 'required|string',
            'shipping_info.tracking_no' => 'required|string',
            'ref_id' => 'sometimes|integer|min:1',
            'order_code' => 'sometimes|required|string',
            'notification_date' => 'required_if:sale_status,shipped',
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        unset($data['access_token']);
        unset($data['items']);
        unset($data['customer']);
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        $sale = parent::update($data, $id, $attribute);
        return $this->find($id);
    }
    

    public function delete($sale_id)
    {
    }
}
