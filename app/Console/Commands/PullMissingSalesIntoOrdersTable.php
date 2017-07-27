<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\Admin\Order;

class PullMissingSalesIntoOrdersTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:pullMissingSalesIntoOrdersTable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command checks for all sales that are missing from the orders table and pushes creates them in the orders and order_items tables.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $orderArray = array();

        DB::table('sales')->chunk(1000, function ($sales) use (&$orderArray){
            foreach ($sales as $sale) {
                // check if exist in orders table
                $order = Order::where('id', $sale->sale_id)->first();

                if (!$order){

                    // insert missing sale
                    $address = trim(preg_replace('/\s+/', ' ', $sale->sale_address));
                    $address = preg_replace('/(MY1)$/', 'MY', $address);
                    $street1 = (!empty($sale->sale_street_1) ? $sale->sale_street_1 : '');
                    $street2 = (!empty($sale->sale_street_2) ? $sale->sale_street_2 : '');
                    $city = (!empty($sale->sale_city) ? $sale->sale_city : '');
                    $state = (!empty($sale->sale_state) ? $sale->sale_state : '');
                    $postcode = (!empty($sale->sale_postcode) ? $sale->sale_postcode : '');
                    $country = (!empty($sale->sale_country) ? $sale->sale_country : '');
                    if (!empty($address)) {
                        /*if (empty($postcode)) {
                            preg_match('/[0-9]{5}(?!.*\s[0-9]{5}\s)/', $sale->sale_address, $postcode);
                            $postcode = trim($postcode[0]);
                            $address = str_replace($postcode, '', $address);
                        }*/
                        $url = 'http://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&region=my';
                        $data = file_get_contents($url);
                        $data_results = json_decode($data, true);
                        if(!empty($data_results['results'])){
                          $add_components = $data_results['results'][0]['address_components'];
                          foreach ($add_components as $add) {
                              if($add['types'][0] == 'postal_code'){
                                  if(empty($postcode)){
                                      $postcode = $add['long_name'];
                                  }
                              }
                              $address = str_replace($postcode, '', strtolower($address));

                              if($add['types'][0] == 'locality'){
                                  if(empty($city)){
                                      $city = $add['long_name'];
                                  }
                              }
                              $address = str_replace(strtolower($city), '', strtolower($address));
                              if($add['types'][0] == 'administrative_area_level_1'){
                                  if(empty($state)){
                                      $state = $add['long_name'];
                                  }
                              }
                              $address = str_replace(strtolower($state), '', strtolower($address));

                              if($add['types'][0] == 'country'){
                                  if(empty($country)){
                                      $country = $add['long_name'];
                                  }
                                  $address = str_ireplace(' my', $add['long_name'], $address);
                              }
                              $address = str_replace(strtolower($country), '', strtolower($address));

                              if($add['types'][0] == 'sublocality_level_1'){
                                  if(empty($street2)){
                                      $street2 = $add['long_name'];
                                  }
                              }
                              $address = str_replace(strtolower($street2), '', strtolower($address));
                          }

                          if(empty($street1)){
                            $street1 = $address;
                          }
                        }
                    }

                    // get order subtotal (item total)
                    $subtotal = DB::table('sales_items')->where('sale_id','=', $sale->sale_id)->where('product_type', '=', 'ChannelSKU')->sum('item_price');

                    // get all hw discounts (bourne by merchant/hubwire)
                    // get all marketplace discounts
                    $items = DB::table('sales_items')->where('sale_id','=', $sale->sale_id)->get();

                    $hw_discount = 0; // sum all item's ori price - listing price + promotionCode/ItemDiscount
                    $tp_discount = 0; // sum of all sale_price (listing price) - sold_price
                    $cart_discount = 0;
                    $total_tax = 0;
                    foreach ($items as $item) {
                        $sale_price = ($item->item_sale_price > 0 ? $item->item_sale_price : $item->item_original_price);
                        if($item->product_type == 'ChannelSKU'){
                            $hw_discount += ($item->item_original_price - $sale_price);
                            $tp_discount += ($sale_price - $item->item_price);
                            $total_tax += $item->item_tax;
                        }
                        if($item->product_type == 'ItemDiscount'){
                            $hw_discount += abs($item->item_price);
                        }

                        if($item->product_type == 'PromotionCode' && $item->item_price > 0){
                            $hw_discount += abs($item->item_price);
                        }elseif($item->product_type == 'PromotionCode' && $item->item_price == 0){
                            $code = DB::table('promotion_code')->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_code.code_id')->where('promotion_code.code_id','=', $item->product_id)->first();
                            if(!empty($code)){
                                $hw_discount += $code->discount_quantity;
                            }
                        }elseif($item->product_type == 'CartDiscount') {
                          $cart_discount = abs($item->item_price);
                        }
                    }

                    $status = ucfirst($sale->sale_status);
                    if($status == 'Cancelled'){
                        $last_status_log = DB::table('order_status_log')->where('order_id','=',$sale->sale_id)->orderBy('created_at','desc')->first();
                        if(!empty($last_status_log)) $status = (ucfirst($last_status_log->to_status) != 'Cancelled')?ucfirst($last_status_log->to_status):ucfirst($last_status_log->from_status);
                    }
                    // insert into orders table
                    $orderId = Order::insertGetId([
                        'id' => $sale->sale_id,
                        'subtotal' => $subtotal,
                        'total' => $sale->sale_total,
                        'shipping_fee' => $sale->sale_shipping,
                        'cart_discount' => $cart_discount,
                        'total_discount' => $sale->sale_discount,
                        'total_tax' => $total_tax,
                        'currency' => $sale->currency,
                        'forex_rate' => $sale->rate,
                        'merchant_id' => $sale->client_id,
                        'channel_id' => $sale->channel_id,
                        'tp_order_id' => $sale->ref_id,
                        'tp_order_code' => $sale->order_code,
                        'tp_source' => '',
                        'status' => $this->getSaleStatusCode($status),
                        'partially_fulfilled' => (!empty($sale->partially_fulfilled) ? true : false),
                        'cancelled_status' => ($sale->sale_status == 'Cancelled' ? true : false),
                        'paid_status' => ( !in_array( ucfirst($status), array('Pending', 'Failed') ) ? true : false ),
                        'payment_type' => $sale->payment_type,
                        'paid_date' => ( !in_array( ucfirst($status), array('Pending', 'Failed') ) ? $sale->created_at : null ),
                        'member_id' => $sale->member_id,
                        'shipping_recipient' => $sale->sale_recipient,
                        'shipping_phone' => $sale->sale_phone,
                        'shipping_street_1' => $street1,
                        'shipping_street_2' => $street2,
                        'shipping_postcode' => $postcode,
                        'shipping_city' => $city,
                        'shipping_state' => $state,
                        'shipping_country' => $country,
                        'consignment_no' => $sale->consignment_no,
                        'shipping_notification_date' => $sale->notification_date,
                        'reserved' => ($sale->sold_qty_cached == 1 ? true : false),
                        'refunded_amount' => $sale->refunded,
                        'tp_extra' => $sale->extra,
                        'created_at' => $sale->created_at,
                        'updated_at' => $sale->updated_at
                    ]);
                    $orderArray[] = $orderId;

                }
          }
      });

        DB::table('sales_items')->chunk(1000, function ($items)  {
            foreach ($items as $item) {
                $orderItem = DB::table('order_items')->where('id', $item->item_id)->first();
                if (!$orderItem) {
                    if($item->product_type != 'CartDiscount'){
                        $itemId = DB::table('order_items')->insertGetId([
                            'id' => $item->item_id,
                            'order_id' => $item->sale_id,
                            'ref_id' => $item->product_id,
                            'ref_type' => $item->product_type,
                            'unit_price' => $item->item_original_price,
                            'sale_price' => ($item->item_sale_price != 0.00 ? $item->item_sale_price : $item->item_original_price),
                            'sold_price' => $item->item_price,
                            'tax_inclusive' => $item->tax_inclusive,
                            'tax_rate' => ($item->tax_inclusive === true ? 6.00 : 0.00),
                            'tax' => $item->item_tax,
                            'original_quantity' => $item->item_original_quantity,
                            'quantity' => $item->item_quantity,
                            'discount' => $item->item_discount,
                            'tp_discount' => '0.00',
                            'weighted_cart_discount' => '0.00',
                            'fulfilled_channel' => $item->decremented_from,
                            'tp_item_id' => $item->ref_id,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at
                         ]);
                      }
                }
            }
        });

        $cart_sales  = Order::where('cart_discount', '>', '0.00')->whereIn('id', $orderArray)->get();
        foreach ($cart_sales as $cs) {
            // get all order items
            $cart_items_no = DB::table('order_items')->where('order_id', '=', $cs->id)->count();
            $p = number_format((1 / $cart_items_no), 2);
            $cart_items = DB::table('order_items')->where('order_id', '=', $cs->id)->get();
            foreach ($cart_items as $ci) {
                DB::table('order_items')->where('id', $ci->id)->update(['weighted_cart_discount' => $cs->cart_discount * $p]);
            }
        }
        $this->info('Done pulling missing sales in to orders table. Total record processed: '.count($orderArray));

    }

    public function getSaleStatusCode($status)
    {
        $status = ucfirst($status);

        if(isset(Order::$statusCode[$status])){
          return Order::$statusCode[$status];
        }else{
          return 0; //unknown
        }

        $this->info('Done pulling missing sales in to orders table. Total record processed: '.count($orderArray));
    }
}
