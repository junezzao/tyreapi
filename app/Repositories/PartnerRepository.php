<?php

namespace app\Repositories;

use App\Repositories\Contracts\PartnerRepositoryContract;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use Response;
use App\Events\ChannelSkuQuantityChange;

class PartnerRepository implements PartnerRepositoryContract
{
    public function getDistributionCenters($partner_id)
    {
        $this->validatePartner($partner_id);
        $distribution_centers =  \DistributionCenter::where('partner_id', '=', $partner_id)->with('channel', 'default_sale_channel')->get();
        $response = [];
        foreach ($distribution_centers as $dc) {
            $response[] = $this->distributionCenterAPIResponse($dc);
        }
        return $response;
    }

    public function getDistributionCenter($dc_id, $partner_id)
    {
        $this->validateDistributionCenter($dc_id, $partner_id);
        $dc =  \DistributionCenter::where('partner_id', '=', $partner_id)
            ->where('distribution_center_id', '=', $dc_id)
            ->with('channel', 'default_sale_channel')->first();
        return $this->distributionCenterAPIResponse($dc);
    }

    public function getDistributionCenterDetails($dc_id, $partner_id, array $fields)
    {
        $this->validateDistributionCenter($dc_id, $partner_id);
        return \DistributionCenter::join('channels', function ($join) {
                    $join->on('channels.channel_id', '=', 'distribution_ch_id');
                })
                ->join('partners', function ($join) {
                    $join->on('partners.partner_id', '=', 'distribution_center.partner_id');
                })
                ->where('distribution_center_id', '=', $dc_id)
        ->where('distribution_center.partner_id', '=', $partner_id)
        ->first($fields);
    }

    public function prepareStockTransferDetails($client)
    {
        $details = new \stdClass();
        $details->do_type = 3;
        $details->remarks =  'Partner Stock Transfer';
        $details->client_id = $client->client_id;
        return $details;
    }

    public function distributionCenterAPIResponse($data)
    {
        $distribution_center = new \stdClass();
        $distribution_center->distribution_center_id = $data->distribution_center_id;
        $distribution_center->min_quantity = $data->min_quantity;
        $distribution_center->name = $data->channel->channel_name;
        $distribution_center->address = $data->channel->channel_address;
        unset($data->channel->client->client_id);
        $distribution_center->client = $data->channel->client;
        return $distribution_center;
    }

    public function getPartnerOrders($partner_id)
    {
        $this->validatePartner($partner_id);
        $dcs = \DistributionCenter::where('partner_id', '=', $partner_id)->lists('distribution_ch_id');
        $sales = \Sales::with('items')->whereIn('distribution_ch_id', $dcs)->get();
        $response = array();
        foreach ($sales as $sale) {
            $response[] = $this->salesAPIResponse($sale);
        }
        return $response;
    }

    public function getDistributionCenterOrders($dc_id, $partner_id)
    {
        $this->validateDistributionCenter($dc_id, $partner_id);
        return \Sales::with('items')->where('distribution_ch_id', '=', $dc_id)->get();
    }

    public function getOrder($order_id, $partner_id)
    {
        $this->validatePartner($partner_id);
        $dcs = \DistributionCenter::where('partner_id', '=', $partner_id)->lists('distribution_ch_id');
        $sale = \Sales::with('items')->where('sale_id', '=', $order_id)->whereIn('distribution_ch_id', $dcs)->firstOrFail();
        $response = $this->salesAPIResponse($sale);
        return $response;
    }

    public function updateOrder($order_id, $inputs, $partner_id)
    {
        $this->validatePartner($partner_id);
        $dcs = \DistributionCenter::where('partner_id', '=', $partner_id)->lists('distribution_ch_id');
        $sale = \Sales::with('items')->where('sale_id', '=', $order_id)->whereIn('distribution_ch_id', $dcs)->firstOrFail();
        $v = \Validator::make($inputs, [
                'order_status' => "required|in:completed,shipped,pending,failed,packing",
                'tracking_number' => 'required_if:order_status,shipped'
            ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $sale->sale_status = $inputs['order_status'];
        if (!empty($inputs['tracking_number']) && $inputs['order_status']==="shipped") {
            $sale->consignment_no = $inputs['tracking_number'];
            $sale->fulfillment_status = 1;
        }
        $sale->save();
        return $this->salesAPIResponse($sale);
    }

    public function returnOrder($order_id, $inputs, $partner_id)
    {
        $this->validatePartner($partner_id);
        $dcs = \DistributionCenter::where('partner_id', '=', $partner_id)->lists('distribution_ch_id');
        $sale = \Sales::with('items')->where('sale_id', '=', $order_id)->whereIn('distribution_ch_id', $dcs)->firstOrFail();
        $rules = ['return_items' => "required|array"];
        if (!empty($inputs['return_items']) && is_array($inputs['return_items'])) {
            foreach ($inputs['return_items'] as $key => $val) {
                $item = \SalesItem::find($val['item_id']);
                $rules['return_items.'.$key.'.item_id'] = 'required|integer|min:1|exists:sales_items,item_id,sale_id,'.$sale->sale_id;
                if (!is_null($item)) {
                    $rules['return_items.'.$key.'.quantity'] = 'required|integer|min:1|max:'.$item->item_quantity;
                } else {
                    $rules['return_items.'.$key.'.quantity'] = 'required|integer|min:1';
                }
            }
        }
        $v = \Validator::make($inputs, $rules);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        \DB::beginTransaction();
        foreach ($inputs['return_items'] as $key => $val) {
            $item = \SalesItem::find($val['item_id']);
            $item->decrement('item_quantity', $val['quantity']);
            $item->save();
            $channel_sku = \ChannelSKU::where('channel_sku_id', '=', $item->product_id)->first();
            // $oldQuantity = $channel_sku->channel_sku_quantity;
            // $channel_sku->increment('channel_sku_quantity', $val['quantity']);
            // $channel_sku->touch();
            // event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $oldQuantity, 'SalesItem', $val['item_id']));
            // Outdated code Missing Return Log (need to revise ref_table when used)
            event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $val['quantity'], 'SalesItems', $val['item_id'], 'increment'));
        }
        \DB::commit();
        $sale = \Sales::with('items')->where('sale_id', '=', $order_id)->first();
        return $this->salesAPIResponse($sale);
    }



    public function validatePartner($partner_id)
    {
        $inputs = ['partner_id' => $partner_id];
        $v = \Validator::make($inputs, [
                'partner_id' => 'required|integer|min:1|exists:partners'
            ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
    }

    public function validateDistributionCenter($dc_id, $partner_id, $field = 'distribution_center_id')
    {
        $inputs = [$field => $dc_id];
        $v = \Validator::make($inputs, [
                $field => 'required|integer|min:1|exists:distribution_center,distribution_center_id,partner_id,'.$partner_id
            ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
    }

    public function getDistributionCenterId($ch_id)
    {
        $dc = \DistributionCenter::where('distribution_ch_id', '=', $ch_id)->first(['distribution_center_id']);
        return $dc->distribution_center_id;
    }

    public function salesAPIResponse($sale)
    {
        $response = new \stdClass();
        $response->order_id = $sale->sale_id;
        $response->order_status = $sale->sale_status;
        $response->customer_name = $sale->sale_recipient;
        $response->customer_address = $sale->sale_address;
        $response->customer_phone = $sale->sale_phone;
        $response->fulfillment_status  = $sale->fulfillment_status;
        $response->tracking_number = $sale->consignment_no;
        $response->distribution_center_id = $this->getDistributionCenterId($sale->distribution_ch_id);

        foreach ($sale->items as $item) {
            if ($item->product_type!=='ChannelSKU') {
                continue;
            }
            $order_item = new \stdClass();
            $channel_sku = \ChannelSKU::find($item->product_id);
            $order_item->item_id = $item->item_id;
            $order_item->sku_id = $channel_sku->sku_id;
            $order_item->product_name = $channel_sku->product->product_name;
            $order_item->hubwire_sku = $channel_sku->sku->hubwire_sku;
            $order_item->barcode = $channel_sku->sku->sku_barcode;
            $order_item->order_quantity = $item->item_quantity;
            $order_item->original_quantity = $item->item_original_quantity;
            $order_item->options = $channel_sku->sku_options;
            // $order_item->fulfill_status = $item->fulfill_status;
            $response->order_items[] = $order_item;
        }
        $response->created_at = $sale->created_at;
        $response->updated_at = $sale->updated_at;
        return $response;
    }
}
