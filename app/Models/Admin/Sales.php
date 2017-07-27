<?php

namespace App\Models\Admin;
use App\Models\Admin\Member;
use App\Models\Admin\Channel;
use App\Models\Admin\SalesItem;
class Sales extends \Eloquent
{
    protected $table = "sales";
    protected $primaryKey = "sale_id";
    // protected $guarded = ['sale_id'];
    protected $fillable = [
                        'member_id',
                        'payment_type',
                        'sale_status',
                        'sale_total',
                        'sale_discount',
                        'sale_shipping',
                        'sale_recipient',
                        'sale_street_1',
                        'sale_street_2',
                        'sale_postcode',
                        'sale_city',
                        'sale_state',
                        'sale_country',
                        'sale_phone',
                        'consignment_no',
                        'order_code',
                        'ref_id',
                        'channel_id',
                        'client_id',
                        'extra',
                        'currency',
                        ];
    
    public function getDates()
    {
        return [];
    }
    
    public function member()
    {
        return $this->belongsTo('App\Models\Admin\Member', 'member_id')->with('addresses');
    }
    
    public function channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'channel_id');
    }
    
    public function items()
    {
        return $this->hasMany('App\Models\Admin\SalesItem', 'sale_id', 'sale_id')->with('product');
    }
    
    public function notes()
    {
        return $this->hasMany('SaleNotes', 'sale_id')
        ->select('sale_id', 'notes', 'admin_name', 'sales_notes.created_at')
        ->join('client_admins', 'client_admins.admin_id', '=', 'sales_notes.admin_id')
        ->orderBy('sales_notes.created_at', 'desc');
    }
    
    public function status_log()
    {
        return $this->hasMany('SaleStatusLog', 'sale_id')
        ->select('sale_id', 'from_status', 'to_status', 'admin_name', 'sale_status_log.created_at')
        ->join('client_admins', 'client_admins.admin_id', '=', 'sale_status_log.admin_id')
        ->orderBy('sale_status_log.created_at', 'desc');
    }
    
    public function store_credit_log()
    {
        return $this->hasMany('StoreCreditsLog', 'sale_id')
        ->select(DB::raw('store_credits_log.*, admin_name'))
        ->join('client_admins', 'client_admins.admin_id', '=', 'store_credits_log.admin_id');
    }
    
    public function scopeSold($query)
    {
        return $query->where(function ($query) {
            $query->where('sales.sale_status', '=', 'paid')
            ->orWhere('sales.sale_status', '=', 'packing')
            ->orWhere('sales.sale_status', '=', 'shipped')
            ->orWhere('sales.sale_status', '=', 'completed');
        });
    }
    
    public function scopeNotSold($query)
    {
        return $query->where(function ($query) {
            $query->where('sales.sale_status', '=', 'pending')
            ->orWhere('sales.sale_status', '=', 'failed');
        });
    }
    
    public function scopeDailyReport($query)
    {
        return $query->select(DB::raw('count(DISTINCT sales.sale_id) as total_orders, 
										sum(sales_items.item_price*sales_items.item_quantity*sales.rate) as sale_total, 
										sum(sales_items.item_quantity) as item_quantity, 
										avg(item_price*sales_items.item_quantity*sales.rate) as average_item_price,
										avg(item_cost*sales_items.item_quantity*sales.rate) as average_cog,
										sum(item_cost*sales_items.item_quantity*sales.rate) as total_cog,
										DATE_FORMAT(sales.created_at, \'%Y-%m-%d\') as sales_date '))
                ->leftjoin('sales_items', function ($query) {
                 $query->on('sales_items.sale_id', '=', 'sales.sale_id')
                 ->where('product_type', '=', 'ChannelSKU');
                })
                ->leftjoin('channel_sku', function ($query) {
                 $query->on('sales_items.product_id', '=', 'channel_sku.channel_sku_id');
                })
                ->leftjoin(DB::raw('(SELECT sku_id, AVG(item_cost*item_quantity) as item_cost FROM purchase_items group by sku_id) as t'), 'channel_sku.sku_id', '=', 't.sku_id')
                ->groupBy(DB::raw('DATE_FORMAT(sales.created_at, \'%Y-%m-%d\')'))
                ->where('sales.client_id', '=', Auth::user()->client_id)
                ->sold();
    }
    
    
    public static function apiResponse($data, $criteria = null)
    {
        
        /*
        [member_id] => 0
        [payment_type] => ipay88
        [sale_total] => 30.48
        [sale_shipping] => 7.16
        [channel_id] => 9874
        [created_at] => 2016-05-19 04:17:30
        [updated_at] => 2016-05-19 04:17:30
        [sale_status] => paid
        [sale_address] => 403 Kaylee Land Suite 198
    South Meagan, ND 86962-3810
        [sale_phone] => 946-226-1283 x721
        [sale_recipient] => Prof. Lysanne Waters
        [client_id] => 4538
        [ref_id] =>
        [order_code] =>
        [consignment_no] =>
        [notification_date] =>
        [sale_discount] => 0
        [shipping_no] => 68050333
        [extra] =>
        [rate] => 1
        [currency] =>
        [sync_status] => 0
        [fulfillment_status] => 0
        [distribution_ch_id] => 0
        [sale_postcode] => 20674-0073
        [sale_country] => Kyrgyz Republic
        */
        if (empty($data->toArray())) {
            return null;
        }
        
        $sales = $data;
        $single = false;
            
        if (empty($sales[0])) {
            $sales = [$sales];
            $single = true;
        }
        
        $result = array();
        foreach ($sales as $sale) {
            $extra = unserialize($sale->extra);
            $response  = new \stdClass();
            $response->id = $sale->sale_id;
            $response->order_number = $sale->order_code;
            $response->order_date = !empty($extra['created_at'])?$extra['created_at']:$sale->created_at;
            $response->total_price = $sale->sale_total;
            $response->total_discount = $sale->sale_discount;
            $response->shipping_fee = $sale->sale_shipping;
            $response->currency = $sale->currency;
            $response->payment_type = $sale->payment_type;
            $response->status = $sale->sale_status;
            $response->created_at =  $sale->created_at;
            $response->updated_at = $sale->updated_at;
            
            $shipping = new \stdClass();
            $shipping->recipient = $sale->sale_recipient;
            $shipping->phone = $sale->sale_phone;
            $shipping->tracking_no = $sale->consignment_no;
            $shipping->address_1 = $sale->sale_street_1;
            $shipping->address_2 = $sale->sale_street_2;
            $shipping->city = $sale->sale_city;
            $shipping->postcode = $sale->sale_postcode;
            $shipping->state = $sale->sale_state;
            $shipping->country = $sale->sale_country;
            $response->shipping_info = $shipping;
                
            $response->items = $sale->items->toAPIResponse();

            if (!empty($sale->member)) {
                $address = !empty($sale->member->address)?\Address::apiResponse($sale->member->address):null;
                // $response->billing_address = $address;
                $response->customer = \Member::apiResponse($sale->member, $criteria);
            }
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
    public static function boot()
    {
        parent::boot();
    }
}
