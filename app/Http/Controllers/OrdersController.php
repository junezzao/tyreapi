<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Repositories\Eloquent\OrderRepository as OrderRepo;
use App\Repositories\Criteria\Order\ByChannel;
use App\Repositories\Criteria\Order\ByMerchant;
use App\Repositories\Criteria\Order\ByStatus;
use App\Repositories\Criteria\Order\CreatedAt;
use App\Repositories\Criteria\Order\SinceId;
use App\Repositories\Criteria\Order\UpdatedAt;
use App\Repositories\Criteria\Order\WithChanges;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Http\Traits\DocumentGeneration;

use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Member;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Services\Mailer;

use Cache;
use App\Helpers\Helper;

class OrdersController extends Controller
{
    use DocumentGeneration;

    private $order;
    private $oauth;
    private $inputs;
    private $timezone;
    private $channel;

    public function __construct()//OrderRepo $order
    {
        // $this->order = $order;
        $this->order = new OrderRepo(new Order, new Mailer);
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::all();
        $this->timezone = config('app.timezone');
        unset($this->inputs['access_token']);
        unset($this->inputs['HTTP_Authorization']);

        if (!empty($this->oauth->authenticatable_type) && $this->oauth->authenticatable_type === 'Channel') {
            $this->channel = Channel::with('channel_detail')->find($this->oauth->authenticatable_id);
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            
            // $this->inputs['client_id'] = $this->oauth->authenticatable->client_id;
            $this->order->pushCriteria(new ByChannel($this->inputs['channel_id']));
            $this->timezone = !empty($this->oauth->authenticatable->timezone)?$this->oauth->authenticatable->timezone:$this->timezone;
        }

    }

    public function index()
    {
        $rules = [
            'status' => 'sometimes|required|string',
            'created_at' => 'sometimes|required|date|date_format:Y-m-d',
            'updated_at' => 'sometimes|required|date|date_format:Y-m-d',
            'sinceid' => 'sometimes|required|integer|min:1',
            'changed' => 'sometimes|required|boolean',
            'start' => 'sometimes|required|integer|min:0',
            'limit' => 'sometimes|required|integer|min:1|max:'.config('api.response.limit')

        ];
        $v = \Validator::make($this->inputs, $rules);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        if (!empty($this->inputs['status'])) {
            $this->order->pushCriteria(new ByStatus($this->inputs['status']));
        }
        if (!empty($this->inputs['created_at'])) {
            $this->order->pushCriteria(new CreatedAt($this->inputs['created_at']));
        }
        if (!empty($this->inputs['sinceid'])) {
            $this->order->pushCriteria(new SinceId($this->inputs['sinceid']));
        }
        if (!empty($this->inputs['updated_at'])) {
            $this->order->pushCriteria(new UpdatedAt($this->inputs['updated_at']));
        }
        if (isset($this->inputs['changed'])) {
            $this->order->pushCriteria(new WithChanges($this->inputs['changed']));
        }
        $order = $this->order->with('items', 'member');
        $count = $order->count();
        $data  = $order->skip(request()->get('start', 0))->take(request()->get('limit', 50))->all() ;
        return response()->json([
            'code'  => config('globals.status_code.OK_STATUS'),
            'start'=> intval(request()->get('start', 0)),
            'limit' => intval(request()->get('limit', 50)),
            'total' => $count,
            'orders'=> $data->toAPIResponse()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'code'  => config('globals.status_code.OK_STATUS'),
            'order' => $this->order->with('items', 'member')->findOrFail($id)->toAPIResponse()
            ]);
    }

    public function store()
    {
        $response = $this->processOrder($this->inputs);
        // \Log::info($response);
        if($response['success']) {
            $order_proc = new OrderProc($this->inputs['channel_id']);
            $response = $order_proc->createOrder($this->inputs['channel_id'], $response);
        }      
        if(!$response['success']){
            $errors =  response()->json(
             array(
                'code' =>  422,
                'error' => ['order'=>[$response['error_desc']]]
            ));
            throw new HttpResponseException($errors);
        }
        
        return response()->json([
            'code'  => config('globals.status_code.OK_STATUS'),
            'order' => $response
            ]);
    }

    public function update($id)
    {
        // $sale = $this->sales->update($this->inputs, $id);
        // return response()->json(\Sales::apiResponse($sale));
    }

    public function destroy($id)
    {
        // return response()->json($this->sales->delete($id));
    }

    public function printDocument($documentType, $orderId) {
        switch ($documentType) {
            case 'shipping_labels':
                $data = $this->generateShippingLabels($orderId);
                break;
            case 'invoice':
                $data = $this->generateInvoices($orderId);
                break;
            case 'tax_invoice':
                $data = $this->generateTaxInvoice($orderId);
                break;
            case 'credit_note':
                $data = $this->printCreditNote($orderId);
                break;
            default:
                $data = array('error' => 'Document type not found.');
                break;
        }


        return response()->json($data);
    }

    private function processOrder($inputs)
    {
        /*
            "order_number": "mynumber",
            "order_date": "2016-08-02 13:01:00",
            "total_price" : 129,
            "total_discount" : 10,
            "shipping_fee" : 10,
            "currency"  : "MYR",
            "payment_type" : "ipay88",
            "status" : "paid",
            "shipping_info": {
                "recipient": "mahadhir bin mohd asnawi",
                "phone": "",
                "tracking_no" : "8012983",
                "address_1": "No 12, Jalan Kiai Khusairi 3",
                "address_2": "Taman Aneka Baru",
                "city" : "Klang",
                "postcode": "41250",
                "state": "Selangor Darul Ehsan",
                "country": "MALAYSIA"
            },
            "items": [
                {
                    "sku_id": "",
                    "quantity": 1,
                    "price": 20.00,
                    "discount" : 5.00,
                    "tax" : 0.00,
                    "tax_inclusive": 1
                },
                {
                    "sku_id": "",
                    "quantity": 1,
                    "price": 20.00,
                    "discount" : 5.00,
                    "tax" : 0.00,
                    "tax_inclusive": 1
                }
            ],
            "customer": {
                "name": "Abu Bakar",
                "email": "abu@email.com",
                "phone": "1287197291872"
            }
        */
        $channel_id  = $this->inputs['channel_id'];
        
        $rules = [
            'order_number'                  =>  'required',
            'order_date'                    =>  'required|date_format:Y-m-d H:i:s',
            // 'total_price'                   =>  'required|numeric|min:0.01',
            // 'total_discount'                =>  'required|numeric|min:0',
            'shipping_fee'                  =>  'required|numeric|min:0',
            'currency'                      =>  'required',
            'payment_type'                  =>  'required',
            'status'                        =>  'required|in:new,paid,failed',
            'shipping_info'                 =>  'required|array',
            'shipping_info.recipient'       =>  'required',
            'shipping_info.phone'           =>  'required',
            'shipping_info.tracking_no'     =>  'string',
            'shipping_info.address_1'       =>  'required',
            'shipping_info.city'            =>  'required',
            'shipping_info.postcode'        =>  'required',
            'shipping_info.state'           =>  'required',
            'shipping_info.country'         =>  'required'
        ];

        $messages = [];

        if(!empty($inputs['items'])){
            foreach($inputs['items'] as $key => $value)
            {
                $rules['items.'.$key.'.sku_id'] = 'required|integer|exists:channel_sku,sku_id,channel_id,'.$channel_id;
                $rules['items.'.$key.'.quantity'] = 'required|integer|min:1';
                $rules['items.'.$key.'.tax'] = 'required|numeric|min:0';
                $rules['items.'.$key.'.tax_inclusive'] = 'required|boolean';
                $rules['items.'.$key.'.price'] = 'required|numeric|min:0';
                $rules['items.'.$key.'.discount'] = 'required|numeric|min:0';
                $rules['items.'.$key.'.tp_item_id'] = 'required';

                $messages['items.'.$key.'.sku_id.required'] = 'The item sku id is required.';
                $messages['items.'.$key.'.sku_id.integer'] = 'The item sku id must be integer.';
                $messages['items.'.$key.'.sku_id.exists'] = 'The item sku id is invalid.';
                $messages['items.'.$key.'.tp_item_id.required'] = 'The item\'s TP item ID is required.';
                  
            }
        } 
          
        $v = \Validator::make($inputs, $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        } 

        /**
        *   Date conversion
        */
        if($this->timezone != 'UTC')
        {
            $inputs['order_date']   = Helper::convertTimeToUTC($inputs['order_date'], $this->timezone);
        }

        $extra_info                 = json_decode($this->channel->channel_detail->extra_info);
        
        $order = new Order;
        $order->tp_order_code       = $inputs['order_number'];
        $order->tp_order_date       = $inputs['order_date'];
        $order->tp_extra            = json_encode(['created_at'=>$order->tp_order_date]);
        $order->tp_source           = 'api';
        $order->shipping_fee        = $inputs['shipping_fee'];
        $order->currency            = $inputs['currency'];
        $order->payment_type        = $inputs['payment_type'];
        $order->status              = strcasecmp($inputs['status'], 'paid') == 0 ? Order::$newStatus : Order::$pendingStatus;
        $order->paid_status         = ($order->status == Order::$newStatus) ? 1 : 0;
        $order->paid_date           = ($order->paid_status == 1) ? $inputs['order_date'] : '';
        $order->cancelled_status    = false;
        $order->shipping_recipient  = $inputs['shipping_info']['recipient'];
        $order->shipping_phone      = $inputs['shipping_info']['phone'];
        $order->shipping_street_1   = $inputs['shipping_info']['address_1'];
        $order->shipping_street_2   = $inputs['shipping_info']['address_2'];
        $order->shipping_postcode   = $inputs['shipping_info']['postcode'];
        $order->shipping_city       = $inputs['shipping_info']['city'];
        $order->shipping_state      = $inputs['shipping_info']['state'];
        $order->shipping_country    = $inputs['shipping_info']['country'];
        $order->shipping_provider   = !empty($extra_info->shipping_provider)?$extra_info->shipping_provider:'';
        $order->consignment_no      = $inputs['shipping_info']['tracking_no'];
        $order->forex_rate          = 1;

        
        $member                 = Member::firstOrNew(['member_email'=>$inputs['customer']['email'],'channel_id'=>$channel_id]);
        $member->member_name    = $inputs['customer']['name'];
        $member->member_type    = 1;
        $member->member_email   = (!empty($inputs['customer']['email'])) ? $inputs['customer']['email'] : $member->member_email;
        $member->member_mobile  = $inputs['customer']['phone'];

        $order->subtotal        = 0;
        $order->total           = 0;
        $order->total_discount  = 0;
        foreach($inputs['items'] as $i_input)
        {
            for($i=0;$i<$i_input['quantity'];$i++)
            {
                $item                   = new OrderItem;
                $channel_sku            = ChannelSKU::where('sku_id','=',$i_input['sku_id'])
                                            ->where('channel_id','=',$channel_id)->first();
                $item->ref_type         = 'ChannelSKU';
                $item->sold_price       = $i_input['price'];
                $item->sale_price       = $channel_sku->channel_sku_promo_price;
                $item->unit_price       = $channel_sku->channel_sku_price;
                $item->tax_inclusive    = $i_input['tax_inclusive'];
                $item->tax_rate         = $item->tax_inclusive?0.06:0.00;
                $item->tax              = $item->tax_inclusive?$i_input['tax']:0.00;
                $item->quantity         = $item->original_quantity = 1;
                $item->discount         = $item->unit_price - $item->sale_price;
                $item->tp_discount      = $i_input['discount'];
                // $item->weighted_cart_discount = 0;
                $item->channel_sku_id   = $channel_sku->channel_sku_id;
                $item->tp_item_id       = $i_input['tp_item_id'];
                $data['items'][]        = $item;
                $order->subtotal        += ($item->sold_price + ((!$item->tax_inclusive)?$item->tax:0));
                $order->total_discount  += ($item->discount + $item->tp_discount);
                
            }
        }
        $order->total       = $order->subtotal + $order->shipping_fee;
        
        $data['order']      = $order;
        $data['member']     = $member;
        $data['success']    = true;

        return $data;
    }
}
