<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertExistingDataToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // order_notes
        /*DB::table('order_notes')->chunk(1000, function($notes) {
        DB::table('order_notes')->chunk(1000, function($notes) {
            foreach ($notes as $note){
                //DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('order_notes')->where('note_type', '=', '')->update([
                  'note_type' => 'General'
                ]);
                //DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });
        */

        // orders
        // get sales data

        DB::table('sales')->chunk(1000, function ($sales) {
            
            foreach ($sales as $sale) {
                //dd($sale);
                // split the addresses
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
                    if (!empty($data_results['results'])) {
                        $add_components = $data_results['results'][0]['address_components'];
                        foreach ($add_components as $add) {
                            if ($add['types'][0] == 'postal_code') {
                                if (empty($postcode)) {
                                    $postcode = $add['long_name'];
                                }
                            }
                            $address = str_replace($postcode, '', strtolower($address));

                            if ($add['types'][0] == 'locality') {
                                if (empty($city)) {
                                    $city = $add['long_name'];
                                }
                            }
                            $address = str_replace(strtolower($city), '', strtolower($address));
                          
                            if ($add['types'][0] == 'administrative_area_level_1') {
                                if (empty($state)) {
                                    $state = $add['long_name'];
                                }
                            }
                            $address = str_replace(strtolower($state), '', strtolower($address));

                            if ($add['types'][0] == 'country') {
                                if (empty($country)) {
                                    $country = $add['long_name'];
                                }
                                $address = str_ireplace(' my', $add['long_name'], $address);
                            }
                            $address = str_replace(strtolower($country), '', strtolower($address));

                            if ($add['types'][0] == 'sublocality_level_1') {
                                if (empty($street2)) {
                                    $street2 = $add['long_name'];
                                }
                            }
                            $address = str_replace(strtolower($street2), '', strtolower($address));
                        }

                        if (empty($street1)) {
                            $street1 = $address;
                        }
                    }
                }
                //dd($address.'| st1: '.$street1.' st2: '.$street2.' city: '.$city.' state: '.$state.' postcode: '.$postcode.' country: '.$country);

                // get order subtotal (item total)
                $subtotal = DB::table('sales_items')->where('sale_id', '=', $sale->sale_id)->where('product_type', '=', 'ChannelSKU')->sum('item_price');
                //dd(number_format($subtotal, 2));

                // get all hw discounts (bourne by merchant/hubwire)
                // get all marketplace discounts                
                $items = DB::table('sales_items')->where('sale_id', '=', $sale->sale_id)->get();
                
                $hw_discount = 0; // sum all item's ori price - listing price + promotionCode/ItemDiscount
                $tp_discount = 0; // sum of all sale_price - sold_price
                $cart_discount = 0;
                $total_tax = 0;
                foreach ($items as $item) {
                    $sale_price = ($item->item_sale_price > 0 ? $item->item_sale_price : $item->item_original_price);
                    if ($item->product_type == 'ChannelSKU') {
                        $hw_discount += ($item->item_original_price - $sale_price);
                        $tp_discount += ($sale_price - $item->item_price);
                        $total_tax += $item->item_tax;
                    }
                    if ($item->product_type == 'ItemDiscount') {
                        $hw_discount += abs($item->item_price);
                    }
                    
                    if ($item->product_type == 'PromotionCode' && $item->item_price > 0) {
                        $hw_discount += abs($item->item_price);
                    } elseif ($item->product_type == 'PromotionCode' && $item->item_price == 0) {
                        $code = DB::table('promotion_code')->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_code.code_id')->where('promotion_code.code_id', '=', $item->product_id)->first();
                        if (!empty($code)) {
                            //$code = $code[0];
                          $hw_discount += $code->discount_quantity;
                        }
                    } elseif ($item->product_type == 'CartDiscount') {
                        $cart_discount = abs($item->item_price);
                    }
                }
                //dd('HW discount: '.$hw_discount. '| TP discounts: ' . $tp_discount );              

                // insert into orders table
                DB::table('orders')->insert([
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
                    'status' => ucfirst($sale->sale_status),
                    'partially_fulfilled' => (!empty($sale->partially_fulfilled) ? true : false),
                    'cancelled_status' => ($sale->sale_status = 'Cancelled' ? true : false),
                    'paid_status' => (!in_array(ucfirst($sale->sale_status), array('Pending', 'Failed')) ? true : false),
                    'payment_type' => $sale->payment_type,
                    'paid_date' => (!in_array(ucfirst($sale->sale_status), array('Pending', 'Failed')) ? $sale->created_at : null),
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
                    'reserved' => false,
                    'refunded_amount' => $sale->refunded,
                    'tp_extra' => $sale->extra,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at
                ]);
            }
        });

        // order_items
        // drop all CartDiscount rows as they have been moved to orders table
        DB::table('sales_items')->chunk(1000, function ($items) {
            foreach ($items as $item) {
                if ($item->product_type != 'CartDiscount') {
                    DB::table('order_items')->insert([
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
        });

        // loop through all orders with cart discount and split them up per item. update order_items table
        $cart_sales  = DB::table('orders')->where('cart_discount', '>', '0.00')->get();
        foreach ($cart_sales as $cs) {
            // get all order items 
          $cart_items_no = DB::table('order_items')->where('order_id', '=', $cs->id)->count();
            $p = number_format((1 / $cart_items_no), 2);
            $cart_items = DB::table('order_items')->where('order_id', '=', $cs->id)->get();
            foreach ($cart_items as $ci) {
                DB::table('order_items')->where('id', $ci->id)->update(['weighted_cart_discount' => $cs->cart_discount * $p]);
            }
        }

        // on done migrating data, drop the sales and sales_items table
        //DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        //Schema::drop('sales');
        //Schema::drop('sales_items');
        //DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('orders')->truncate();
        DB::table('order_items')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
