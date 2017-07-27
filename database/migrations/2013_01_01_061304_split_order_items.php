<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\OrderItem;

class SplitOrderItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // create backup
        Schema::dropIfExists('before_split_order_items');
        DB::statement('CREATE TABLE before_split_order_items LIKE order_items');
        DB::Statement("INSERT INTO before_split_order_items SELECT * from order_items");

        Schema::dropIfExists('before_split_refund_log');
        DB::statement('CREATE TABLE before_split_refund_log LIKE refund_log');
        DB::Statement("INSERT INTO before_split_refund_log SELECT * from refund_log");

        // $orderItems = DB::table('order_items')->get();
        $deleteOrderItems = array();

        DB::table('order_items')->chunk(500, function($orderItems) use (&$deleteOrderItems){
            foreach($orderItems as $orderItem){
                $refund = false;
                $newOrderItemIds = array();
                //dd($orderItem->original_quantity);
                if((int)($orderItem->original_quantity) > 1 && $orderItem->ref_type == 'ChannelSKU'){
                    // \Log::info('post-order item id ' . $orderItem->id . ' has a quantity of ' . $orderItem->original_quantity);
                    // check if order item id exists in refund_log
                    // $query = 'SELECT * FROM refund_log WHERE sale_item_id = ' . $orderItem->id;
                    $refundItems = DB::table('refund_log')->where('sale_item_id', '=', $orderItem->id)->get();

                    // check if order item id exists in refund_log
                    if(count($refundItems) > 0){
                        $refund = true;
                    }

                    // split order item
                    for($i = 0 ; $i < $orderItem->original_quantity ; $i++){
                        $newOrderItem = new OrderItem;
                        $newOrderItem->order_id                 = $orderItem->order_id;
                        $newOrderItem->ref_id                   = $orderItem->ref_id;
                        $newOrderItem->ref_type                 = $orderItem->ref_type;
                        $newOrderItem->unit_price               = $orderItem->unit_price;
                        $newOrderItem->sale_price               = $orderItem->sale_price;
                        $newOrderItem->sold_price               = $orderItem->sold_price;
                        $newOrderItem->tax_inclusive            = $orderItem->tax_inclusive;
                        $newOrderItem->tax_rate                 = $orderItem->tax_rate;
                        $newOrderItem->tax                      = $orderItem->tax;
                        $newOrderItem->original_quantity        = 1;
                        $newOrderItem->quantity                 = 1;
                        $newOrderItem->discount                 = $orderItem->discount;
                        $newOrderItem->tp_discount              = $orderItem->tp_discount;
                        $newOrderItem->weighted_cart_discount   = $orderItem->weighted_cart_discount;
                        $newOrderItem->fulfilled_channel        = $orderItem->fulfilled_channel;                      
                        $newOrderItem->tp_item_id               = $orderItem->tp_item_id;
                        $newOrderItem->created_at               = $orderItem->created_at;
                        $newOrderItem->updated_at               = $orderItem->updated_at;
                        $newOrderItem->save();
                        $newOrderItemIds[] = $newOrderItem->id;
                    }

                    // if the order item has a refund, split the refund log if its qty is more than 1
                    if($refund){
                        foreach($refundItems as $refundItem){
                            if($refundItem->return_qty == 0){
                                $saleItemId = array_pop($newOrderItemIds);
                                $updatedOrderItem = DB::table('refund_log')->where('log_id', '=', $refundItem->log_id)->update(['sale_item_id' => $saleItemId]);
                                $updatedOrderItem = DB::table('order_items')->where('id', '=', $saleItemId)->update(['quantity' => 0]);
                            }
                            else{
                                for($q = 0 ; $q < $refundItem->return_qty ; $q++){
                                    $saleItemId = array_pop($newOrderItemIds);
                                    DB::table('refund_log')->insert([
                                        'member_id'            => $refundItem->member_id,
                                        'admin_id'             => $refundItem->admin_id,
                                        'sale_id'              => $refundItem->sale_id,
                                        'refund_type'          => $refundItem->refund_type,
                                        'amount'               => ((float)$refundItem->amount)/((int)$refundItem->return_qty),
                                        'remark'               => $refundItem->remark,
                                        'ref_id'               => $refundItem->ref_id,
                                        'sale_item_id'         => $saleItemId,
                                        'return_qty'           => 1,
                                        'status'               => $refundItem->status,
                                        'completed_at'         => $refundItem->completed_at,
                                        'created_at'           => $refundItem->created_at,
                                        'updated_at'           => $refundItem->updated_at,
                                    ]);

                                    $updatedOrderItem = DB::table('order_items')->where('id', '=', $saleItemId)->update(['quantity' => 0]);
                                }
                                DB::table('refund_log')->where('log_id', '=', $refundItem->log_id)->delete();
                            }   
                        }
                    }
                    $deleteOrderItems[] = $orderItem->id;
                    // DB::table('order_items')->where('id', '=', $orderItem->id)->delete();
                    // $orderItem->delete();
                }else{
                    // do nothing
                }
            }
        });
        // delete order items
        foreach($deleteOrderItems as $orderItemId){
            DB::table('order_items')->where('id', '=', $orderItemId)->delete();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
