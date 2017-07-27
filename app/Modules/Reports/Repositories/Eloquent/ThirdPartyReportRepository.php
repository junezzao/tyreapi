<?php
namespace App\Modules\Reports\Repositories\Eloquent;

use App\Modules\Reports\Repositories\Contracts\ThirdPartyReportRepositoryContract;
use App\Models\User;
use App\Models\Admin\ThirdPartyReport;
use App\Models\Admin\ThirdPartyReportLog;
use App\Models\Admin\ThirdPartyReportRemark;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Merchant;
use App\Jobs\GenerateReport;
use App\Repositories\GenerateReportRepository;
use App\Repositories\Repository as Repository;
use App\Exceptions\ValidationException as ValidationException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use Artisan;
use Validator;
use DB;
use Helper;
use Carbon\Carbon;

define("DISCARDED_CUTOFF_DATE", "2016-12-01");

class ThirdPartyReportRepository extends Repository implements ThirdPartyReportRepositoryContract
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    protected $userId;

    protected $adminTz;

    public function __construct()
    {
        $this->model = new ThirdPartyReport;
        $this->userId = Authorizer::getResourceOwnerId();
        $this->adminTz = User::where('id', '=', $this->userId)->value('timezone');
    }
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'App\Models\Admin\ThirdPartyReport';
    }

    public function process(array $data) {
        /*
        $nonCompletedCount = ThirdPartyReport::where('status', '<>', 'Completed')->count();
        if ($nonCompletedCount > 0) {
            return array(
                'success'   => false,
                'errors'    => array('There are items that are not yet completed. Please complete all items before uploading a new file.')
            );
        }
        */

        $salesChannelTypes = ChannelType::where('type', '=', 'Sales')->whereNotIn('name', ['Shopify', 'Shopify POS'])->get();
        $supportedChannelTypes = array();

        foreach ($salesChannelTypes as $channel) {
            $supportedChannelTypes[$channel->name] = $channel->id;
        }

        $rules = array(
            '*.channel_type'  => 'required|string|in:' . implode(',', array_keys($supportedChannelTypes)) . '|exists:channel_types,name,type,Sales',
            '*.tp_order_code' => 'required',
            // '*.tp_item_id'    => 'required',
            // '*.hubwire_sku'   => 'string',
            '*.product_id'    => 'numeric',
            '*.quantity'      => 'numeric',
            '*.item_status'   => 'required|in:Verified,Cancelled,Returned',
            '*.unit_price'    => 'numeric',
            '*.sale_price'    => 'numeric',
            '*.sold_price'    => 'numeric',
            '*.channel_fees'  => 'required|numeric',
            '*.channel_shipping_fees'  => 'required|numeric',
            '*.channel_payment_gateway_fees'  => 'required|numeric',
            '*.net_payout'    => 'required|numeric',
            //'*.payment_date'  => 'date_format:d/m/Y',
            '*.paid_status'   => 'string',  
        );

        $errorMessages = array(
            '*.channel_type.in'             => ':attribute must be a supported channel type (i.e. Lazada, Zalora or 11Street)',
            '*.item_status.in'              => ':attribute must be either Verified, Cancelled or Returned',
        );

        $validator = Validator::make($data['data'], $rules, $errorMessages);

        if ($validator->fails()) {
            return array(
                'success'   => false,
                'errors'    => $validator->errors()
            );
        }

        $counts = array(
            'uploaded'          => count($data['data']),
            'matched'           => 0,
            'order_not_found'   => 0,
            'item_not_found'    => 0,
            'discrepancies'     => 0,
        );

        $media = $data['media'];
        $data = $data['data'];
        $duplicates = array(
            'verified'              => array(),
            'cancelled_returned'    => array(),
            'paid_status_updated'   => array(),
            'channel_fees_updated'  => array(),
            'channel_shipping_fees_updated'  => array(),
            'channel_payment_gateway_fees_updated'  => array(),
            'net_payout_updated'    => array(),
        );

        $tpReportsUpdated = array();

        $dataLeftovers = array();

        DB::beginTransaction();

        foreach ($data as $tpData) {
            $tpData['last_attended_by'] = $this->userId;
            $tpData['created_by'] = $this->userId;
            $tpData['media_id'] = $media['media_id'];
            $tpData['channel_type_id'] = $supportedChannelTypes[$tpData['channel_type']];
            $tpData['payment_date'] = strcasecmp(trim($tpData['paid_status']),'PAID') == 0 ? Carbon::now()->format('Y-m-d') : NULL;
            if(empty($tpData['quantity'])){
                $tpData['quantity'] = 1;
            }
            $tpData['sold_price'] = round(($tpData['sold_price']/$tpData['quantity']), 2);
            $tpData['channel_fees'] = round(($tpData['channel_fees']/$tpData['quantity']), 2);
            $tpData['channel_shipping_fees'] = round(($tpData['channel_shipping_fees']/$tpData['quantity']), 2);
            $tpData['channel_payment_gateway_fees'] = round(($tpData['channel_payment_gateway_fees']/$tpData['quantity']), 2);
            $tpData['net_payout'] = round(($tpData['net_payout']/$tpData['quantity']), 2);

            $totalQuantity = (!empty($tpData['quantity'])) ? $tpData['quantity'] : 1;
            $tpData['quantity'] = 1;

            if(strtoupper($tpData['paid_status']) == 'PAID'){
                $tpData['paid_status'] = 1;
                $tpData['payment_date'] = Carbon::now()->format('Y-m-d');
            }elseif(strtoupper($tpData['paid_status']) == 'UNPAID'){
                $tpData['paid_status'] = 0;
                $tpData['payment_date'] = NULL;
            }else{
                // everything else is considered to be not paid
                $tpData['paid_status'] = 0;
            }

            //find order_item_id 
            if (empty($tpData['tp_item_id'])) {
                $order = Order::select('orders.id', 'orders.channel_id', 'channels.channel_type_id')
                            ->join('channels', 'channels.id', '=', 'orders.channel_id')
                            ->where('channels.channel_type_id', '=', $tpData['channel_type_id'])
                            ->where('orders.tp_order_code', '=', $tpData['tp_order_code'])
                            ->first();

                $orderItems = OrderItem::where('order_id', '=', $order['id'])->get();
                $matchHubwireSku = DB::table('sku_mapping')->where('old_hubwire_sku', '=', trim($tpData['hubwire_sku']))->where('completed_at', '!=', Null)->first();
                if (empty($matchHubwireSku)) {
                    foreach ($orderItems as $orderItem) {
                        if ($orderItem->ref->sku->hubwire_sku == trim($tpData['hubwire_sku'])) {
                            $tpData['tp_item_id'] = $orderItem->tp_item_id;
                            break;
                        }
                    }
                }else{
                    foreach ($orderItems as $orderItem) {
                        if ($orderItem->ref->sku->hubwire_sku == $matchHubwireSku->new_hubwire_sku) {
                            $tpData['tp_item_id'] = $orderItem->tp_item_id;
                            break;
                        }
                    }
                }
            }

            /*
             * Find if there's already a record exists for the item
             */
            $tpReports = ThirdPartyReport::where('tp_order_code', '=', $tpData['tp_order_code'])
                                            ->where('tp_item_id', '=', $tpData['tp_item_id'])
                                            ->where('channel_type_id', '=', $tpData['channel_type_id'])
                                            ->whereNotIn('id', $tpReportsUpdated)
                                            ->get();

            $tpReportsCount = $tpReports->count();

            $tpReportsIgnore = array();

            // $function = is_null($tpReports) ? 'create' : 'update';

            if (!is_null($tpReports)) {
                // if trreport is paid, ignore it, else update it accordingly
                foreach($tpReports as $tpReport){
                    if($tpReport->paid_status == 0 && $tpData['paid_status'] == 1){
                        $duplicates['paid_status_updated'][] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ', Order Item '. $tpReport->order_item_id .')';
                        $tpReport->paid_status = 1;
                        $tpReport->payment_date = Carbon::now()->format('Y-m-d');
                        $tpReport->save();
                    }
                    // overwrite the channel fees with upload data
                    if(($tpReport->channel_fees != $tpData['channel_fees']) && ($tpReport->paid_status == 1 && $tpData['paid_status'] == 1)){
                        $duplicates['channel_fees_updated'][] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ', Order Item '. $tpReport->order_item_id .')';
                        $tpReport->channel_fees = $tpData['channel_fees'];
                        $tpReport->payment_date = Carbon::now()->format('Y-m-d');
                        $tpReport->save();
                    }

                    // overwrite the channel shipping fees with upload data
                    if(($tpReport->channel_shipping_fees != $tpData['channel_shipping_fees']) && ($tpReport->paid_status == 1 && $tpData['paid_status'] == 1)){
                        $duplicates['channel_shipping_fees_updated'][] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ', Order Item '. $tpReport->order_item_id .')';
                        $tpReport->channel_shipping_fees = $tpData['channel_shipping_fees'];
                        $tpReport->payment_date = Carbon::now()->format('Y-m-d');
                        $tpReport->save();
                    }

                    // overwrite the channel payment gateway fees with upload data
                    if(($tpReport->channel_payment_gateway_fees != $tpData['channel_payment_gateway_fees']) && ($tpReport->paid_status == 1 && $tpData['paid_status'] == 1)){
                        $duplicates['channel_payment_gateway_fees_updated'][] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ', Order Item '. $tpReport->order_item_id .')';
                        $tpReport->channel_payment_gateway_fees = $tpData['channel_payment_gateway_fees'];
                        $tpReport->payment_date = Carbon::now()->format('Y-m-d');
                        $tpReport->save();
                    }

                    // overwrite the net payout with upload data
                    if(($tpReport->net_payout != $tpData['net_payout']) && ($tpReport->paid_status == 1 && $tpData['paid_status'] == 1)){
                        $duplicates['net_payout_updated'][] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ', Order Item '. $tpReport->order_item_id .')';
                        $tpReport->net_payout = $tpData['net_payout'];
                        $tpReport->save();
                    }
                     // will not update if UPLOAD item status is verified, or DB item status is cancelled or returned.
                    if (strtoupper($tpData['item_status']) == 'VERIFIED') {
                        $duplicates['verified'][$tpReport->id] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ')';
                        $tpReportsIgnore[] = $tpReport->id;
                        continue 1;
                    }
                    else if (strtoupper($tpReport->item_status) == 'CANCELLED' || strtoupper($tpReport->item_status) == 'RETURNED') {
                        $duplicates['cancelled_returned'][$tpReport->id] = $tpData['tp_item_id'] . ' (Order ' . $tpData['tp_order_code'] . ')';
                        $tpReportsIgnore[] = $tpReport->id;
                        continue 1;
                    }
                    // else {
                    //     $tpReportsUpdated[] = $tpReport->id;
                    // }
                }
            }

            $totalQuantity = $totalQuantity - $tpReportsCount;

            /*
             * Find if order/order item exists in DB
             */
            $order = Order::select('orders.*')
                            ->join('channels', 'channels.id', '=', 'orders.channel_id')
                            ->where('channels.channel_type_id', '=', $tpData['channel_type_id'])
                            ->where('orders.tp_order_code', '=', $tpData['tp_order_code'])
                            ->first();
            
            if (is_null($order) || empty($order)) {
                $tpData['status'] = 'Not Found';
                // $newTpReport = $this->model->{$function}($tpData);

                // create new tp reports 
                for($i = 0 ; $i < $totalQuantity ; $i++){
                    $tpData['created_by'] = $this->userId;
                    $newTpReport = $this->model->create($tpData);
                    $this->createRemark($newTpReport->id, 0, 'Order with third party order id ' . $tpData['tp_order_code'] . ' cannot be found in Arc.', 'error');
                }

                // update existing reports
                foreach($tpReports as $tpReport){
                    //check the status to update the payment date.
                    if ($tpReport->item_status == 'Verified' && $tpData['item_status'] == 'Returned') {
                        $tpReport->payment_date = Carbon::now()->format('Y-m-d');
                        $tpReport->save();
                    }

                    if(!in_array($tpReport->id, $tpReportsIgnore)){
                        $tpReportsUpdated[] = $tpReport->id;
                        $updateResult = $tpReport->update($tpData);
                        $this->createRemark($tpReport->id, 0, 'Order with third party order id ' . $tpData['tp_order_code'] . ' cannot be found in Arc.', 'error');
                    }
                }

                $counts['order_not_found']++;
                continue;
            }

            $orderItems = OrderItem::where('order_id', '=', $order->id)
                                    ->where('tp_item_id', '=', $tpData['tp_item_id'])
                                    ->get();

            $orderItemCount = count($orderItems);

            if ($orderItemCount == 0) {
                $tpData['status'] = 'Not Found';

                for($i = 0 ; $i < $totalQuantity ; $i++){
                    $tpData['created_by'] = $this->userId;
                    $newTpReport = $this->model->create($tpData);
                    $tpReportsUpdated[] = $newTpReport->id;
                    $this->createRemark($newTpReport->id, 0, 'Order item with third party item id ' . $tpData['tp_item_id'] . ' cannot be found in third party order ' . $tpData['tp_order_code'] . '.', 'error');
                }

                foreach($tpReports as $tpReport){
                    if(!in_array($tpReport->id, $tpReportsIgnore)){
                        $tpReportsUpdated[] = $tpReport->id;
                        $updateResult = $tpReport->update($tpData);
                        $this->createRemark($tpReport->id, 0, 'Order item with third party item id ' . $tpData['tp_item_id'] . ' cannot be found in third party order ' . $tpData['tp_order_code'] . '.', 'error');
                    }
                }

                $counts['item_not_found']++;
                continue;
            }

            $counts['matched']++;

            /*
             * Validation
             */

            foreach($tpReports as $tpReport){
                $remarks = array();
                $currentOrderItem = $orderItems->where('id', $tpReport->order_item_id)->pop();
                if(in_array($tpReport->id, $tpReportsIgnore)){
                    if (!empty($tpData['hubwire_sku']) && $tpData['hubwire_sku'] != $currentOrderItem->ref->sku->hubwire_sku) {
                        $matchHubwireSku = DB::table('sku_mapping')->where('old_hubwire_sku', '=', $tpData['hubwire_sku'])->where('completed_at', '!=', Null)->first();
                        if (empty($matchHubwireSku)) {
                            $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                        }else{
                            if ($currentOrderItem->ref->sku->hubwire_sku != $matchHubwireSku->new_hubwire_sku) {
                                $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                            }
                        }
                    }

                    if (!empty($tpData['product_id']) && $tpData['product_id'] != $currentOrderItem->ref->sku->product_id) {
                        $remarks[] = 'This order item does not belong to the product id entered.';
                    }

                    if (!empty($tpData['unit_price']) && $tpData['unit_price'] != $currentOrderItem->unit_price) {
                        $remarks[] = 'The RRP entered does not match the order item record in Arc.';
                    }

                    if (!empty($tpData['sale_price']) && $tpData['sale_price'] != $currentOrderItem->sale_price) {
                        $remarks[] = 'The listing price entered does not match the order item record in Arc.';
                    }

                    //if (strtoupper($tpData['item_status']) == 'VERIFIED' && $tpData['sold_price'] != $currentOrderItem->sold_price) {
                    //    $remarks[] = 'The customer paid price entered does not match the order item record in Arc.';
                    //}
                    //else if (strtoupper($tpData['item_status']) != 'VERIFIED') {
                    //    if ($tpData['sold_price'] != -$currentOrderItem->sold_price) {
                    //        $remarks[] = 'The customer paid price (returned/cancelled) entered does not tally with the price sold for this item.';
                    //    }
                    //}

                    /* Removed. We do not use negative sold price in system upon return
                    if ($tpReport->sold_price + $tpData['sold_price'] != 0) {
                        $remarks[] = 'The customer paid price entered does not offset the previous value.';
                    }*/

                    /* Removed. Channel fee for returns might not offset initial upload entirely as shipping fee is still being charged
                    if ($tpReport->channel_fees + $tpData['channel_fees'] != 0) {
                        $remarks[] = 'The channel fees entered does not offset the previous value.';
                    }

                    if ($tpReport->net_payout + $tpData['net_payout'] != 0) {
                        $remarks[] = 'The net payout entered does not offset the previous value.';
                    }*/

                    // Channel Fee will be positive when uploading for delivered items.
                    // Channel Fee will be negative when uploading for returned items. 
                    // Balance difference between the two will be the shipping fee that is still being charged to merchant
                    if(strtoupper($tpData['item_status']) == 'RETURNED') {
                        if ($tpData['channel_fees'] > 0)
                            $netPayout = -(($tpData['sale_price'] - $tpData['sale_price']) - round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                        else
                            $netPayout = -($tpData['sale_price'] + round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                    } elseif(strtoupper($tpData['item_status']) == 'CANCELLED') {
                        $netPayout = 0;
                    } else { 
                        $netPayout = round(($tpData['sale_price'] - $tpData['channel_fees'] - $tpData['channel_shipping_fees'] - $tpData['channel_payment_gateway_fees'] ), 2) ;
                    }

                    if ($netPayout != floatval($tpData['net_payout'])) {
                        $remarks[] = 'The net payout entered does not tally with [listing price - channel fees].';
                    }

                    foreach ($remarks as $remark) {
                        $this->createRemark($tpReport->id, 0, $remark, 'error');
                    }
                    
                    // check remarks
                    $unresolved = ThirdPartyReportRemark::where('tp_report_id',$tpReport->id)
                                    ->where('resolve_status',0)
                                    ->where('type','error');

                    if (count($remarks) > 0) {
                        $tpData['status'] = 'Unverified';
                        $counts['discrepancies']++;
                    }
                    else if($unresolved->count() == 0){
                        $tpData['status'] = 'Verified';
                    }

                    $updateResult = $tpReport->update($tpData);
                    $tpReportsUpdated[] = $tpReport->id;
                    
                }
            }

            $orderItemsByStatus = $orderItems->where('status', $tpData['item_status']);

            for($i = 0 ; $i < $totalQuantity ; $i++){
                $remarks = array();
                $tpData['order_id'] = $order->id;
                $tpData['status'] = 'Unverified';

                // $orderItem = $orderItems[0];
                if($orderItemsByStatus->count() > 0){
                    $orderItem = $orderItemsByStatus->pop();
                    $tpData['order_item_id'] = $orderItem->id;
                }else{
                    $leftovers = $totalQuantity - $i;
                    $tpData['quantity'] = $leftovers;
                    $dataLeftovers[] = $tpData;
                    continue 2;
                }

                // if ($orderItemCount == 1 && strtoupper($tpData['item_status']) != strtoupper($orderItem->status)) {
                //     $remarks[] = 'The item status entered for does not match the item status in Arc.';
                // }

                if (!empty($tpData['hubwire_sku']) && $tpData['hubwire_sku'] != $orderItem->ref->sku->hubwire_sku) {
                    $matchHubwireSku = DB::table('sku_mapping')->where('old_hubwire_sku', '=', $tpData['hubwire_sku'])->where('completed_at', '!=', Null)->first();
                    if (empty($matchHubwireSku)) {
                        $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                    }else{
                        if ($orderItem->ref->sku->hubwire_sku != $matchHubwireSku->new_hubwire_sku) {
                            $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                        }
                    }
                }

                if (!empty($tpData['product_id']) && $tpData['product_id'] != $orderItem->ref->sku->product_id) {
                    $remarks[] = 'This order item does not belong to the product id entered.';
                }

                if (!empty($tpData['unit_price']) && $tpData['unit_price'] != $orderItem->unit_price) {
                    $remarks[] = 'The RRP entered does not match the order item record in Arc.';
                }

                if (!empty($tpData['sale_price']) && $tpData['sale_price'] != $orderItem->sale_price) {
                    $remarks[] = 'The listing price entered does not match the order item record in Arc.';
                }

                //if (strtoupper($tpData['item_status']) == 'VERIFIED' && $tpData['sold_price'] != $orderItem->sold_price) {
                //    $remarks[] = 'The customer paid price entered does not match the order item record in Arc.';
                //}
                //else if (strtoupper($tpData['item_status']) != 'VERIFIED') {
                //    if ($tpData['sold_price'] != -$orderItem->sold_price) {
                //        $remarks[] = 'The customer paid price (returned/cancelled) entered does not tally with the price sold for this item.';
                //    }
                //}

                if(strtoupper($tpData['item_status']) == 'RETURNED') {
                        if ($tpData['channel_fees'] > 0)
                            $netPayout = -(($tpData['sale_price'] - $tpData['sale_price']) - round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                        else
                            $netPayout = -($tpData['sale_price'] + round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                } else { 
                    $netPayout = round(($tpData['sale_price'] - $tpData['channel_fees']), 2);
                }

                if ($netPayout != floatval($tpData['net_payout'])) {
                    $remarks[] = 'The net payout entered does not tally with [listing price - channel fees].';
                }

                /* Add this to all other tp report record if it doesnt tally
                if ($totalQuantity != $orderItemCount) {
                    $remarks[] = 'The quantity entered does not tally with the order item record in Arc.';
                }
                */

                if (count($remarks) > 0) {
                    $counts['discrepancies']++;
                }

                $tpData['created_by'] = $this->userId;
                $newTpReport = $this->model->create($tpData);
                $tpReportsUpdated[] = $newTpReport->id;
                // $tpReportId = is_null($tpReport) ? $newTpReport->id : $this->model->id;

                foreach ($remarks as $remark) {
                    $this->createRemark($newTpReport->id, 0, $remark, 'error');
                }

                //copy upload channel_fee to order_item table channel_fee
                OrderItem::where('order_id', '=', $order->id)
                                    ->where('tp_item_id', '=', $tpData['tp_item_id'])
                                    ->update(['channel_fee' => abs($tpData['channel_fees'])]);
            }
        }
        DB::commit();

        DB::beginTransaction();

        foreach($dataLeftovers as $tpData){
            $remarks = array();

            // get order items that does not exist in third_party_report
            $leftoverOrderItems = OrderItem::select('order_items.*', 'third_party_report.id as tp_id', 'third_party_report.order_item_id')
                                    ->leftJoin('third_party_report', 'order_items.id', '=', 'third_party_report.order_item_id')
                                    ->where('order_items.order_id', '=', $tpData['order_id'])
                                    ->where('order_items.tp_item_id', '=', $tpData['tp_item_id'])
                                    ->whereNull('third_party_report.order_item_id')
                                    ->get();

            $totalQuantity = $tpData['quantity'];

            for($i = 0 ; $i < $totalQuantity ; $i++){
                $tpData['quantity'] = 1;

                if($leftoverOrderItems->count() > 0){
                    $orderItem = $leftoverOrderItems->pop();
                    $tpData['order_item_id'] = $orderItem->id;
                    $tpData['status'] = 'Unverified';
                }else{
                    // state that the order item does not exist in our db
                    unset($orderItem);
                    $tpData['order_item_id'] = NULL;
                    $tpData['status'] = 'Not Found';

                    $remarks[] = 'Order item with third party item id ' . $tpData['tp_item_id'] . ' cannot be found in third party order ' . $tpData['tp_order_code'] . '.';
                }

                if(isset($orderItem)){
                    if (!empty($tpData['hubwire_sku']) && $tpData['hubwire_sku'] != $orderItem->ref->sku->hubwire_sku) {
                        $matchHubwireSku = DB::table('sku_mapping')->where('old_hubwire_sku', '=', $tpData['hubwire_sku'])->where('completed_at', '!=', Null)->first();
                        if (empty($matchHubwireSku)) {
                            $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                        }else{
                            if ($orderItem->ref->sku->hubwire_sku != $matchHubwireSku->new_hubwire_sku) {
                                $remarks[] = 'The hubwire sku entered does not belong to this order item.';
                            }
                        }
                    }

                    if (!empty($tpData['product_id']) && $tpData['product_id'] != $orderItem->ref->sku->product_id) {
                        $remarks[] = 'This order item does not belong to the product id entered.';
                    }

                    if (!empty($tpData['unit_price']) && $tpData['unit_price'] != $orderItem->unit_price) {
                        $remarks[] = 'The RRP entered does not match the order item record in Arc.';
                    }

                    if (!empty($tpData['sale_price']) && $tpData['sale_price'] != $orderItem->sale_price) {
                        $remarks[] = 'The listing price entered does not match the order item record in Arc.';
                    }

                    /*if (strtoupper($tpData['item_status']) == 'VERIFIED' && $tpData['sold_price'] != $orderItem->sold_price) {
                        $remarks[] = 'The customer paid price entered does not match the order item record in Arc.';
                    }
                    else if (strtoupper($tpData['item_status']) != 'VERIFIED') {
                        if ($tpData['sold_price'] != -$orderItem->sold_price) {
                            $remarks[] = 'The customer paid price (returned/cancelled) entered does not tally with the price sold for this item.';
                        }
                    }*/

                    if(strtoupper($tpData['item_status']) == 'RETURNED') {
                        if ($tpData['channel_fees'] > 0)
                            $netPayout = -(($tpData['sale_price'] - $tpData['sale_price']) - round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                        else
                            $netPayout = -($tpData['sale_price'] + round($tpData['channel_fees'], 2) - round($tpData['channel_shipping_fees'], 2));
                    } else { 
                        $netPayout = round(($tpData['sale_price'] - $tpData['channel_fees'] - $tpData['channel_shipping_fees'] - $tpData['channel_payment_gateway_fees']), 2);
                    }

                    if ($netPayout != floatval($tpData['net_payout'])) {
                        $remarks[] = 'The net payout entered does not tally with [listing price - channel fees].';
                    }

                    $remarks[] = 'The item status entered for does not match the item status in Arc.';
                }
                

                $counts['discrepancies']++;
                if(!is_null($order)){
                    //copy upload channel_fee to order_item table channel_fee
                    OrderItem::where('order_id', '=', $order->id)
                                        ->where('id', '=', $tpData['order_item_id'])
                                        ->update(['channel_fee' => abs($tpData['channel_fees'])]);
                }
                                    
                $tpData['created_by'] = $this->userId;
                $newTpReport = $this->model->create($tpData);
                $tpReportsUpdated[] = $newTpReport->id;

                foreach ($remarks as $remark) {
                    $this->createRemark($newTpReport->id, 0, $remark, 'error');
                }
            }
        }

        DB::commit();

        return array(
            'success'       => true,
            'duplicates'    => $duplicates,
            'counts'        => $counts
        );
    }

    public function createRemark($tpReportId, $userId, $remark, $type) {
        return ThirdPartyReportRemark::create([
            'tp_report_id'  => $tpReportId,
            'added_by'      => $userId,
            'remarks'       => $remark,
            'type'          => $type,
        ]);
    }

    public function search($request) {
        // \Log::info(print_r($request->all(), true));die;
        $mode = $request->input('columns')[17]['search']['value'];
        $query = '';                                // query to fetch results
        $table = 'third_party_report';              // choose which table to use depending on which tab was clicked
        $countersSelectString = '';                 // query for counter stats (num orders, order items, gmv)

        switch($mode) {
            // show order items that haven't been processed
            case config('globals.tp_report_tab_type.PENDING_PAYMENT_TP'):
                // don't include order items that are in the pending payment to merchant tab
                $table = 'order_items';
                $ids = ThirdPartyReport::select(\DB::raw('distinct order_id'))
                            ->whereNotNull('order_id')
                            ->pluck('order_id');

                $query = \DB::table('order_items')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'channels.channel_type_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->where('orders.shipped_date','>=', DISCARDED_CUTOFF_DATE)
                            ->whereNotIn('order_items.order_id', $ids);

                // columns to select from the table
                $colsToSelect = 'order_items.*,
                                 channel_types.name as channel_type,
                                 orders.tp_order_code,
                                 orders.tp_order_date,
                                 orders.shipped_date as shipped_date,
                                 order_items.id as order_item_id,
                                 merchants.name as merchant_name';

                $countersSelectString = ", COUNT(distinct $table.order_id) as num_orders,
                                         COUNT($table.id) as num_order_items,
                                         format(SUM($table.sold_price), 2) as gmv_arc";

                break;

            // show all third party reports that haven't been completed or discarded(unpaid)
            case config('globals.tp_report_tab_type.PENDING_PAYMENT_MERCHANT'):
                $query = \DB::table('third_party_report')
                            ->leftjoin(\DB::raw('(select tp_report_id,
                                                    group_concat(remarks SEPARATOR "<br/> ") as remarks
                                                    from third_party_report_remarks
                                                    where type = \'error\'
                                                    and resolve_status = 0
                                                    group by third_party_report_remarks.tp_report_id
                                                    ) as third_party_report_remarks'),
                                                'third_party_report.id', '=', 'third_party_report_remarks.tp_report_id')
                            ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'third_party_report.channel_type_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('users', 'users.id', '=', 'third_party_report.last_attended_by')
                            ->where("$table.status", '<>', 'Completed')
                            ->where("$table.status", '<>', 'Discarded')
                            ->where("$table.paid_status", '=', '0')
                            ->where("third_party_report.deleted_at", null);

                $colsToSelect = 'third_party_report.*,
                                 third_party_report_remarks.remarks as remarks,
                                 orders.tp_order_date,
                                 orders.shipped_date,
                                 channel_types.name as channel_type,
                                 merchants.name as merchant_name,
                                 users.first_name as last_attended_by';

                $countersSelectString = ", COUNT(distinct $table.order_id) as num_orders,
                                         COUNT($table.order_item_id) as num_order_items,
                                         format(SUM($table.sold_price), 2) as gmv_arc,
                                         format(SUM($table.net_payout), 2) as gmv_uploaded";

                break;

            // show all third party reports that haven't been completed or discarded(paid)
            case config('globals.tp_report_tab_type.PAID_MERCHANT'):
                $query = \DB::table('third_party_report')
                            ->leftjoin(\DB::raw('(select tp_report_id,
                                                    group_concat(remarks SEPARATOR "<br/> ") as remarks
                                                    from third_party_report_remarks
                                                    where type = \'error\'
                                                    and resolve_status = 0
                                                    group by third_party_report_remarks.tp_report_id
                                                    ) as third_party_report_remarks'),
                                                'third_party_report.id', '=', 'third_party_report_remarks.tp_report_id')
                            ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'third_party_report.channel_type_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('users', 'users.id', '=', 'third_party_report.last_attended_by')
                            ->where("$table.status", '<>', 'Completed')
                            ->where("$table.status", '<>', 'Discarded')
                            ->where("$table.paid_status", '=', '1')
                            ->where("third_party_report.deleted_at", null);

                $colsToSelect = 'third_party_report.*,
                                 third_party_report_remarks.remarks as remarks,
                                 orders.tp_order_date,
                                 orders.shipped_date,
                                 channel_types.name as channel_type,
                                 merchants.name as merchant_name,
                                 users.first_name as last_attended_by';

                $countersSelectString = ", COUNT(distinct $table.order_id) as num_orders,
                                         COUNT($table.order_item_id) as num_order_items,
                                         format(SUM($table.sold_price), 2) as gmv_arc,
                                         format(SUM($table.net_payout), 2) as gmv_uploaded";

                break;

            // show completed third party reports
            case config('globals.tp_report_tab_type.COMPLETED'):
                $query = \DB::table('third_party_report')
                                ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                                ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                                ->leftjoin('channel_types', 'channel_types.id', '=', 'third_party_report.channel_type_id')
                                ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                                ->leftjoin('users', 'users.id', '=', 'third_party_report.last_attended_by')
                                ->where("$table.status", '=', 'Completed')
                                ->where("third_party_report.updated_at", '>=', Carbon::now()->subMonth(6))
                                ->where("third_party_report.deleted_at", null);

                $colsToSelect = 'third_party_report.*,
                                 orders.tp_order_date,
                                 orders.shipped_date,
                                 channel_types.name as channel_type,
                                 merchants.name as merchant_name,
                                 users.first_name as last_attended_by';
                break;

            // return empty array if the mode given is not recognized
            default:
                $response['success'] = true;
                $response['records'] = array();
                $response['recordsFiltered'] = 0;
                $response['recordsTotal'] = 0;
                $response['counters'] = array();
                return $response;
        }

        // count total number of records
        $totalRecords = clone $query;
        $totalRecords = $totalRecords->select(\DB::raw('count(*) as total'))->get();

        $remarksFilter = false;
        // search parameters
        $searchVals = $request->get('columns');
        foreach($searchVals as $searchVal) {
            if ($searchVal['search']['value']!='' && $searchVal['name']!="mode") {
                switch ($searchVal['name']) {
                    case "created_at":
                    case "updated_at":
                    case "tp_order_date":
                    case "shipped_date":
                        $dates = explode(" - ", $searchVal['search']['value']);
                        $dates[0] = Helper::convertTimeToUTC($dates[0] . '00:00:00', $this->adminTz);
                        $dates[1] = Helper::convertTimeToUTC($dates[1] . '23:59:59', $this->adminTz);

                        // if searching by created_at or updated_at, specify sql table
                        $field = ($searchVal['name']=='created_at' || $searchVal['name'] =='updated_at') ? $table.'.'.$searchVal['name'] : $searchVal['name'];
                        $query = $query->whereBetween($field, $dates);
                        break;

                    case "tp_order_code":
                        $sqlTable = ($mode==config('globals.tp_report_tab_type.PENDING_PAYMENT_MERCHANT')) ? 'third_party_report.' : 'orders.';
                        $query = $query->where($sqlTable.$searchVal['name'], 'LIKE', '%'.$searchVal['search']['value'].'%');
                        break;

                    case "tp_item_id":
                    case "order_id":
                        $query = $query->where($table.'.'.$searchVal['name'], 'LIKE', '%'.$searchVal['search']['value'].'%');
                        break;

                    case "order_item_id":
                        // in third_party_report, the order item id column is called order_item_id whereas it's just id in order_items
                        $field = ($table=='order_items') ? 'id' : $searchVal['name'];
                        $query = $query->where($table.'.'.$field, '=', $searchVal['search']['value']);
                        break;

                    case "channel_type":
                        $field = ($table=='order_items') ? 'channel_types.id' : 'third_party_report.channel_type_id';
                        $query = $query->where($field, '=', $searchVal['search']['value']);
                        break;

                    case "merchant_name":
                        $query = $query->where('merchants.id', '=', $searchVal['search']['value']);
                        break;

                    case "remarks":
                        if(config('globals.tp_report_tab_type.PENDING_PAYMENT_TP') != $mode){
                            $remarksFilter = true;
                            $remarksQuery = $searchVal['search']['value'];
                        }
                        break;

                    default:
                        $query = $query->where($table.'.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        break;
                }
            }
        }

        // get number of filtered records and counter stats (num orders, order items, gmv)
        // select statement depends on which tab was clicked
        $count = clone $query;
        $count = $count->select(\DB::raw("count(*) as total$countersSelectString"))
                        ->get();

        if($remarksFilter)
            $query = $query->havingRaw('remarks LIKE "%'.$remarksQuery.'%"');

        $query = $query->select(\DB::raw($colsToSelect))
                        ;

        // sort records
        if ($request->input('order')) {
            $colNum = $request->input('order')[0]['column'];
            $colName = $request->input("columns")[$colNum]['name'];
            $query = $query->orderBy($colName, $request->input('order')[0]['dir']);
        }
        else
            $query = $query->orderBy('created_at', 'asc');

        $response['recordsFiltered'] = count($query->get());
        $query = $query->skip($request->input('start', 0))
                        ->take($request->input('length', 10))->get();
        
        $response['success'] = true;
        $response['records'] = $query;
        // $response['recordsFiltered'] = isset($count[0]->total)? $count[0]->total: 0;
        // \Log::info($query->toSql());
        $response['recordsTotal'] = isset($totalRecords[0]->total)? $totalRecords[0]->total: 0;
        $response['counters'] = $count;

        return $response;
    }

    // function to retrieve numbers for the badge counters in the tab panel title
    public function counters($inputs) {

        $ids = ThirdPartyReport::select(\DB::raw('distinct order_id'))
                            ->whereNotNull('order_id')
                            ->pluck('order_id');

        $pendingTpPayment = OrderItem::leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->where('orders.shipped_date','>=', DISCARDED_CUTOFF_DATE)
                            ->whereNotIn('order_items.order_id', $ids)
                            ->select(\DB::raw('count(*) as total'))
                            ->get();

        $pendingPaymentToMerchant = \DB::table('third_party_report')
                                        ->where('status', '<>', 'Completed')
                                        ->where('status', '<>', 'Discarded')
                                        ->where('paid_status', '=', '0')
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();

        $paidToMerchant = \DB::table('third_party_report')
                                        ->where('status', '<>', 'Completed')
                                        ->where('status', '<>', 'Discarded')
                                        ->where('paid_status', '=', '1')
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();

        $paid_status = $inputs['selectedTabIndex'] == 1 ? 0 : 1;
        
        $notFound = \DB::table('third_party_report')
                                        ->where('status', '=', 'Not Found')
                                        ->where('paid_status', '=', $paid_status)
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();
        
        $verified = \DB::table('third_party_report')
                                        ->where('status', '=', 'Verified')
                                        ->where('paid_status', '=', $paid_status)
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();

        $unverified = \DB::table('third_party_report')
                                        ->where('status', '=', 'Unverified')
                                        ->where('paid_status', '=', $paid_status)
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();

        $completed = \DB::table('third_party_report')
                                        ->where('status', '=', 'Completed')
                                        ->where('paid_status', '=', $paid_status)
                                        ->where('deleted_at', null)
                                        ->select(\DB::raw('count(*) as total'))
                                        ->get();



        $response['pending_tp_payment'] = isset($pendingTpPayment[0]->total)? $pendingTpPayment[0]->total: 0;
        $response['pending_payment_to_merchant'] = isset($pendingPaymentToMerchant[0]->total)? $pendingPaymentToMerchant[0]->total: 0;
        $response['paid_to_merchant'] = isset($paidToMerchant[0]->total)? $paidToMerchant[0]->total: 0;
        $response['num_verified_items_unpaid'] = $this->countVerifiedOrderItems(0);
        $response['num_verified_items_paid'] = $this->countVerifiedOrderItems(1);
        $response['not_found'] = isset($notFound[0]->total)? $notFound[0]->total: 0;
        $response['verified'] = isset($verified[0]->total)? $verified[0]->total: 0;
        $response['unverified'] = isset($unverified[0]->total)? $unverified[0]->total: 0;
        $response['completed'] = isset($completed[0]->total)? $completed[0]->total: 0;

        return $response;
    }

    public function completeVerifiedOrderItems($status) {
        $numRowsAffected = ThirdPartyReport::where('status', '=', 'Verified')->where('paid_status', '=', $status[0])->update(['status' => 'Completed']);
        $response['success'] = true;
        $response['numRecordsAffected'] = $numRowsAffected;
        $response['numVerifiedItems_unpaid'] = $this->countVerifiedOrderItems(0);
        $response['numVerifiedItems_paid'] = $this->countVerifiedOrderItems(1);

        return $response;
    }

    public function verify($id){
        $remarks = ThirdPartyReportRemark::where('tp_report_id', $id)
                                            ->where('type', 'error')
                                            ->where('resolve_status', 0)
                                            ->get();

        if($remarks->count() > 0){
            $response = array(
                'error' => 'There are still unresolved remark errors.',
            ); 
        }else{
            DB::beginTransaction();

            $tpReport = ThirdPartyReport::findOrFail($id);

            if(strtoupper($tpReport->status) != 'NOT FOUND' ){
                $tpReport->status = 'Verified';
                $tpReport->save();

                Activity::log('ThirdPartyReport '. $id .' has been verified.', $this->userId);
                $response = array(
                    'success' => true,
                    'item' => $tpReport
                );
            }else{
                $response = array(
                    'error' => 'Matching order item not found.',
                ); 
            }

            DB::commit();
        }

        return $response;
    }

    public function bulk_moveTo($data){
        $orderItems = OrderItem::whereIn('id', json_decode($data['ids'], true))->get();
        $success = array();
        foreach ($orderItems as $orderItem) {
            $channel = Channel::where('id', '=', $orderItem['order']['channel_id'])->first();
                $tpRecord =  array(
                    "media_id"                  => null,
                    "channel_type_id"           => $channel['channel_type_id'],
                    "tp_order_code"             => $orderItem['order']['tp_order_code'],
                    "order_id"                  => $orderItem['order']['id'],
                    "tp_item_id"                => empty($orderItem['tp_item_id'])? $orderItem['id'] : $orderItem['tp_item_id'],
                    "order_item_id"             => $orderItem['id'],
                    "hubwire_sku"               => $orderItem['ref']['sku']['hubwire_sku'],
                    "product_id"                => $orderItem['ref']['sku']['product_id'],
                    "quantity"                  => $orderItem['quantity'],
                    "item_status"               => $orderItem['status'],
                    "unit_price"                => $orderItem['unit_price'],
                    "sale_price"                => $orderItem['sale_price'],
                    "sold_price"                => $orderItem['sold_price'],
                    "channel_fees"              => $orderItem['channel_fee'],
                    "net_payout"                => floatval($orderItem['sale_price']-$orderItem['channel_fee']),
                    "net_payout_currency"       => $orderItem['order']['currency'],
                    "paid_status"               => 1,
                    "payment_date"              => Carbon::now()->toDateString(),//$previousMonthWithoutTime,
                    "tp_payout_ref"             => null,
                    "status"                    => ($data['option'] == 'Completed') ? "Completed" : "Verified",
                    "merchant_payout_amount"    => "0.00",
                    "merchant_payout_currency"  => null,
                    "merchant_payout_status"    => null,
                    "hw_payout_bank"            => null,
                    "merchant_payout_date"      => null,
                    "merchant_payout_ref"       => null,
                    "merchant_bank"             => null,
                    "merchant_payout_method"    => null,
                    "merchant_invoice_no"       => null,
                    "created_by"                => '0',
                    "last_attended_by"          => null,
                    );
                
                $findTpRecord = ThirdPartyReport::where('order_item_id', '=', $orderItem['id'])->first();
                if (is_null($findTpRecord)) {
                    ThirdPartyReport::create($tpRecord);
                    $create[$orderItem['id']] = true;
                }else{
                    $create[$orderItem['id']] = false;
                }
            }
            $response['create'] = $create;
        return $response;
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        $remarks = array();
        $tpReport = $this->model->find($id);
        $matching_item = false;

        $rules = array(
            'third_party_item_details.unit_price'       => 'numeric',
            'third_party_item_details.sale_price'       => 'numeric',
            'third_party_item_details.sold_price'       => 'numeric',
            'hubwire_item_details.unit_price'           => 'numeric',
            'hubwire_item_details.sale_price'           => 'numeric',
            'hubwire_item_details.sold_price'           => 'numeric',
            'hubwire_item_details.tax'                  => 'numeric',
            'hubwire_item_details.min_guarantee'        => 'sometimes|numeric',
            'third_party_payment.net_payout'            => 'numeric',
            'third_party_payment.net_payout_currency'   => 'string|size:3',
            'third_party_payment.payment_date'          => 'date_format:Y-m-d',
            'third_party_payment.channel_fees'          => 'numeric',
            'third_party_payment.channel_shipping_fees' => 'numeric',
            'third_party_payment.channel_payment_gateway_fees' => 'numeric',
            'merchant_payment.merchant_payout_amount'   => 'numeric',
            'merchant_payment.merchant_payout_currency' => 'string|size:3',
            'merchant_payment.merchant_payout_date'     => 'date_format:Y-m-d',
            'hubwire_fee.hw_fee'                        => 'numeric',
            'hubwire_fee.hw_commission'                 => 'numeric',
            'hubwire_fee.misc_fee'                      => 'numeric'
        );
        if ($tpReport->order_item_id == '' || $tpReport->order_item_id == NULL) {
            $rules = array(
            'order_item_id'                             => 'numeric|exists:order_items,id|unique:third_party_report,order_item_id'
            );
            $matching_item = true;
        }elseif ($tpReport->order_item_id == $data['order_item_id']) {
            $rules = array(
            'order_item_id'                             => 'numeric|exists:order_items,id'
            );
        }
        $messages = array(
            'third_party_payment.payment_date.date_format'      => 'Third Party Payout Date format must match: YYYY-mm-dd',
            'merchant_payment.merchant_payout_date.date_format' => 'Merchant Payout Date format must match: YYYY-mm-dd'
        );

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return array(
                'success' => false,
                'errors'  => $validator->errors()
            );
        }

        $tpReportUpdateData = array();

        if(isset($data['order_item_id']) && !empty($data['order_item_id'])) {
            $tpReportUpdateData['order_item_id'] = $data['order_item_id'];
            $tpReportUpdateData['order_id'] = OrderItem::find($data['order_item_id'])->order->id;
        }

        if(isset($data['third_party_payment']))
            $tpReportUpdateData = array_merge($tpReportUpdateData, $data['third_party_payment']);
        if(isset($data['merchant_payment']))
            $tpReportUpdateData = array_merge($tpReportUpdateData, $data['merchant_payment']);
        if(isset($data['third_party_item_details']))
            $tpReportUpdateData = array_merge($tpReportUpdateData, $data['third_party_item_details']);

        if(is_null($tpReport->order_item_id) && empty($data['order_item_id'])){
            $tpReportUpdateData['status'] = 'Not Found';
        }else{
            $tpReportUpdateData['status'] = 'Unverified';
        }

        Activity::log('ThirdPartyReport '. $id .' has been set to unverified.', $this->userId);

        //check the paid status to update the payment date.
        if ($tpReport->paid_status == 0 && $tpReportUpdateData['paid_status'] == 1) {
            $tpReportUpdateData['payment_date'] = Carbon::now()->format('Y-m-d');
        }
// \Log::info($tpReport->item_status);
// \Log::info($tpReportUpdateData['item_status']);
        //check the status to update the payment date.
        if ($tpReport->item_status == 'Verified' && $tpReportUpdateData['item_status'] == 'Returned') {
            $tpReportUpdateData['payment_date'] = Carbon::now()->format('Y-m-d');
        }

        // unable to use update() as it does not trigger model events.
        // $this->model->where('id', $id)->update($tpReportUpdateData);
        $tpReport = $this->model->find($id);
        foreach($tpReportUpdateData as $attribute => $value) {
            if (empty($tpReport->{$attribute}) && empty($value)) {
                continue;
            }

            $tpReport->{$attribute} = $value;
        }
        $tpReport->last_attended_by = $this->userId;
        $tpReport->save();

        //$data['hubwire_item_details']['channel_fee'] = $data['third_party_payment']['channel_fees'];
        // if($matching_item) unset($data['hubwire_item_details']['min_guarantee']);
        $orderItemUpdateData = array();
        // added additional checking, if attaching tp report to an order item, do not update hubwire fee to prevent overwriting order item's original hw_fee - Chris
        if(isset($data['hubwire_fee']) && !$matching_item){
            $orderItemUpdateData = array_merge($orderItemUpdateData, $data['hubwire_fee']);
        }
        if(isset($data['hubwire_item_details']))
            $orderItemUpdateData = array_merge($orderItemUpdateData, $data['hubwire_item_details']);

        // unable to use update() as it does not trigger model events.
        // OrderItem::where('id', $tpReport->order_item_id)->update($orderItemUpdateData);
        if(!empty($tpReport->order_item_id)){
            $orderItem = OrderItem::find($tpReport->order_item_id);
            foreach($orderItemUpdateData as $attribute => $value){
                if($orderItem->{$attribute} != $value){
                    // log it down
                    $log = array(
                        'tp_report_id'  => $id,
                        'old_value'     => $orderItem->{$attribute},
                        'new_value'     => $value,
                        'field'         => 'order_items.'.$attribute,
                        'modified_by'   => $this->userId
                    );
                    ThirdPartyReportLog::create($log);
                }
                $orderItem->{$attribute} = $value;
            }
            $orderItem->save();

            // fields checking
            if( $orderItem->status != $data['third_party_item_details']['item_status'] ){
                $remarks[] = 'The item status entered for does not match the item status in Arc.';
            }
        }

        // Activity::log('ThirdPartyReport ' . $id . ' has been updated.', $this->userId);

        if(!isset($data['hubwire_item_details'])){
            $data['hubwire_item_details']['sold_price'] = $orderItem->sold_price;
        }

        if( $data['third_party_item_details']['item_status'] == 'Verified' ){
            if( isset($data['third_party_item_details']['sale_price']) 
                && isset($data['hubwire_item_details']['sale_price']) 
                && ($data['third_party_item_details']['sale_price'] != $data['hubwire_item_details']['sale_price']) )
                $remarks[] = 'The listing price entered does not match the order item record in Arc.';
        }/*elseif( $data['third_party_item_details']['item_status'] == 'Returned' ){
            // since it is returned, sold price should be negative
            if( isset($data['third_party_item_details']['sold_price'])
                && isset($data['hubwire_item_details']['sold_price'])
                && ($data['third_party_item_details']['sold_price'] != -$data['hubwire_item_details']['sold_price']) )
                $remarks[] = 'The customer paid price (returned/cancelled) entered does not tally with the price sold for this item.';
        }elseif( $data['third_party_item_details']['item_status'] == 'Cancelled' ){
            // since it is cancelled, marketplace should receive RM0.00
            if( isset($data['third_party_item_details']['sold_price'])
                && ($data['third_party_item_details']['sold_price'] != 0) )
                $remarks[] = 'The customer paid price (returned/cancelled) entered does not tally with the price sold for this item.';

        }*/
        if(strtoupper($data['third_party_item_details']['item_status']) == 'RETURNED') {
            if ($data['third_party_payment']['channel_fees'] > 0)
                $netPayout = -(($data['third_party_item_details']['sale_price'] - $data['third_party_item_details']['sale_price']) - round($data['third_party_payment']['channel_fees'], 2));
            else
                $netPayout = -($data['third_party_item_details']['sale_price'] + round($data['third_party_payment']['channel_fees'], 2));
        } elseif(strtoupper($data['third_party_item_details']['item_status']) == 'CANCELLED') {
            $netPayout = 0;
        } else { 
            $netPayout = round(($data['third_party_item_details']['sale_price'] - $data['third_party_payment']['channel_fees'] - $data['third_party_payment']['channel_shipping_fees'] - $data['third_party_payment']['channel_payment_gateway_fees']), 2);
        }

        if( $netPayout !=  $data['third_party_payment']['net_payout']){
            $remarks[] = 'The net payout entered does not tally with [listing price - channel fees].';
        }

        if(isset($data['remark']) && !empty($data['remark'])) {
            $this->createRemark($id, $this->userId, $data['remark'], 'general');
        }

        foreach($remarks as $remark){
            $this->createRemark($id, 0, $remark, 'error');
        }

        DB::commit();

        return array(
            'success' => true,
            'item' => $tpReport
        );
    }

    public function countVerifiedOrderItems($status=null) {
        return ThirdPartyReport::where('status', '=', 'Verified')->where('paid_status','=',$status)->count();
    }

    public function exportTaxInvoice($request) {
        
        if(1==0){
            # code...
            $response['success'] = true;
            $response['data'] = $data;
        }
        else {
            $response['success'] = false;
            $response['message'] = 'There are no records to be exported for the selected option.';
        }
        return $response;
    }

    public function export($request) {
        $ids = json_decode($request->input('ids'));
        $tab = $request->input('tab');
        $items = '';

        // if exporting items pending third party payment
        if ($tab == config('globals.tp_report_tab_type.PENDING_PAYMENT_TP')) {
            $option = $request->input('option');
            $items = \DB::table('order_items')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'channels.channel_type_id')
                            ->leftjoin('issuing_companies', 'issuing_companies.id', '=', 'channels.issuing_company')
                            ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                            ->leftjoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as color, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Colour") as color_option'), 'color_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as size, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Size") as size_option'), 'size_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin('products', 'sku.product_id', '=', 'products.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                            ->whereIn('order_items.id', $ids)
                            ->select(\DB::raw('order_items.*,
                                                orders.tp_order_code,
                                                orders.tp_order_date,
                                                orders.currency,
                                                orders.consignment_no,
                                                orders.shipped_date,
                                                channels.name as channel_name,
                                                channel_types.name as channel_type,
                                                merchants.name as merchant_name,
                                                issuing_companies.gst_reg,
                                                sku.hubwire_sku,
                                                sku.sku_supplier_code,
                                                color_option.color,
                                                size_option.size,
                                                products.name as product_name,
                                                brands.name as brand_name'
                                            ))
                            ->get();
        }

        // if exporting items pending payment to merchant
        else if ($tab == config('globals.tp_report_tab_type.PENDING_PAYMENT_MERCHANT')) {
            $option = $request->input('option');
            $items = \DB::table('third_party_report')
                            ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'channels.channel_type_id')
                            ->leftjoin('issuing_companies', 'issuing_companies.id', '=', 'channels.issuing_company')
                            ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                            ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as color, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Colour") as color_option'), 'color_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as size, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Size") as size_option'), 'size_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin('products', 'sku.product_id', '=', 'products.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id');

            switch ($option) {
                case 'Selected':
                    $items = $items->whereIn('third_party_report.id', $ids);
                    break;

                case 'All':
                    $items = $items->where('third_party_report.status', '<>', 'Completed')
                                   ->where('third_party_report.status', '<>', 'Discarded')
                                   ->where('third_party_report.paid_status', '=', '0');
                    break;

                case 'Verified':
                case 'Unverified':
                case 'Not Found':
                    $items = $items->where('third_party_report.status', '=', $option)->where('third_party_report.paid_status', '=', '0');
                    break;
                    
                case 'Tax Invoice Data':
                    $items = $items->whereIn('third_party_report.id', $ids);
                    break;

                default:
                    $response['success'] = false;
                    $response['message'] = 'There are no records to be exported for the selected option.';
                    return $response;
            }

            $items = $items->select(\DB::raw('order_items.*,
                                                orders.tp_order_code,
                                                orders.tp_order_date,
                                                orders.currency,
                                                orders.consignment_no,
                                                orders.shipped_date,
                                                channels.name as channel_name,
                                                channel_types.name as channel_type,
                                                merchants.name as merchant_name,
                                                issuing_companies.gst_reg,
                                                sku.hubwire_sku,
                                                sku.sku_supplier_code,
                                                color_option.color,
                                                size_option.size,
                                                products.name as product_name,
                                                brands.name as brand_name,
                                                third_party_report.net_payout,
                                                third_party_report.channel_fees,
                                                third_party_report.item_status'
                                            ))
                            ->get();
        }

        // if exporting items paid to merchant
        else if ($tab == config('globals.tp_report_tab_type.PAID_MERCHANT')) {
            $option = $request->input('option');
            $items = \DB::table('third_party_report')
                            ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'channels.channel_type_id')
                            ->leftjoin('issuing_companies', 'issuing_companies.id', '=', 'channels.issuing_company')
                            ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                            ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as color, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Colour") as color_option'), 'color_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as size, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Size") as size_option'), 'size_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin('products', 'sku.product_id', '=', 'products.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id');

            switch ($option) {
                case 'Selected':
                    $items = $items->whereIn('third_party_report.id', $ids);
                    break;

                case 'All':
                    $items = $items->where('third_party_report.status', '<>', 'Completed')
                                   ->where('third_party_report.status', '<>', 'Discarded')
                                   ->where('third_party_report.paid_status', '=', '1');
                    break;

                case 'Verified':
                case 'Unverified':
                case 'Not Found':
                    $items = $items->where('third_party_report.status', '=', $option)->where('third_party_report.paid_status', '=', '1');
                    break;
                    
                case 'Tax Invoice Data':
                    $items = $items->whereIn('third_party_report.id', $ids);
                    break;

                default:
                    $response['success'] = false;
                    $response['message'] = 'There are no records1234567u to be exported for the selected option.';
                    return $response;
            }

            $items = $items->select(\DB::raw('order_items.*,
                                                orders.tp_order_code,
                                                orders.tp_order_date,
                                                orders.currency,
                                                orders.consignment_no,
                                                orders.shipped_date,
                                                channels.name as channel_name,
                                                channel_types.name as channel_type,
                                                merchants.name as merchant_name,
                                                issuing_companies.gst_reg,
                                                sku.hubwire_sku,
                                                sku.sku_supplier_code,
                                                color_option.color,
                                                size_option.size,
                                                products.name as product_name,
                                                brands.name as brand_name,
                                                third_party_report.net_payout,
                                                third_party_report.channel_fees,
                                                third_party_report.item_status'
                                            ))
                            ->get();
        }

        //for complete
        else if ($tab == config('globals.tp_report_tab_type.COMPLETED')) {
            $option = $request->input('option');
            $items = \DB::table('third_party_report')
                            ->leftjoin('order_items', 'order_items.id', '=', 'third_party_report.order_item_id')
                            ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                            ->leftjoin('merchants', 'merchants.id', '=', 'order_items.merchant_id')
                            ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                            ->leftjoin('channel_types', 'channel_types.id', '=', 'channels.channel_type_id')
                            ->leftjoin('issuing_companies', 'issuing_companies.id', '=', 'channels.issuing_company')
                            ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                            ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as color, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Colour") as color_option'), 'color_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin(\DB::raw('(SELECT option_value as size, sku_id
                                                  FROM sku_combinations
                                                  LEFT JOIN sku_options on `sku_options`.`option_id` = `sku_combinations`.`option_id`
                                                  WHERE option_name = "Size") as size_option'), 'size_option.sku_id', '=', 'sku.sku_id')
                            ->leftjoin('products', 'sku.product_id', '=', 'products.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                            ->whereIn('third_party_report.id', $ids)
                            ->select(\DB::raw('order_items.*,
                                                orders.tp_order_code,
                                                orders.tp_order_date,
                                                orders.currency,
                                                orders.consignment_no,
                                                orders.shipped_date,
                                                channels.name as channel_name,
                                                channel_types.name as channel_type,
                                                merchants.name as merchant_name,
                                                issuing_companies.gst_reg,
                                                sku.hubwire_sku,
                                                sku.sku_supplier_code,
                                                color_option.color,
                                                size_option.size,
                                                products.name as product_name,
                                                brands.name as brand_name,
                                                third_party_report.net_payout,
                                                third_party_report.channel_fees,
                                                third_party_report.item_status'
                                            ))
                            ->get();
        }

        if (!(is_null($items) || empty($items))) {
            $data = array();
            foreach ($items as $item) {
                if($item->tax_inclusive == true) {
                    $soldAmount = $item->sold_price;
                    $soldAmountWithoutGst = $item->sold_price - $item->tax;
                } else {
                    $soldAmount = $item->sold_price + $item->tax;
                    $soldAmountWithoutGst = $item->sold_price;
                }

                $discount = ($item->sale_price > 0) ? $item->unit_price - $item->sale_price : 0;
                $item_sale_price = ($item->sale_price == 0 ? $item->unit_price:$item->sale_price);

                if ($option == "Tax Invoice Data") {
                    $reportData = [
                    'Merchant' => $item->merchant_name,
                    'Channel' => $item->channel_name,
                    'Channel Type' => $item->channel_type,
                    'Third Party Order Date' => !is_null($item->tp_order_date) ? Carbon::createFromFormat('Y-m-d H:i:s', $item->tp_order_date)->setTimezone($this->adminTz)->format('d/m/Y') : '',
                    'Order Completed Date' => !is_null($item->shipped_date) ? Carbon::createFromFormat('Y-m-d H:i:s', $item->shipped_date)->setTimezone($this->adminTz)->format('d/m/Y') : '',
                    'Tax Invoice Number' => DB::table('order_invoice')->where('order_id','=',$item->order_id)->value('tax_invoice_no'),
                    'Order No' => $item->order_id,
                    'Third Party Order No' => $item->tp_order_code,
                    //'Brand' => $item->brand_name,
                    //'Hubwire SKU' => $item->hubwire_sku,
                    //'Supplier SKU' => $item->sku_supplier_code,
                    //'Product Name' => $item->product_name,
                    //'Size' => $item->size,
                    //'Color' => $item->color,
                    //'Quantity' => $item->original_quantity,
                    'Currency' => $item->currency,
                    'Listing Price (Excl. GST)'=> number_format($item_sale_price/(1+$item->tax_rate), 2),
                    'Listing Price GST'=> number_format($item_sale_price*$item->tax_rate, 2),
                    'Total Sales (Excl. GST)' => ($item->gst_reg == 1) ? number_format($soldAmountWithoutGst * $item->original_quantity, 2): number_format($item->sold_price * $item->original_quantity, 2),
                    //'HW Discounts' => number_format($discount * $item->original_quantity, 2), 
                    'Total Sales GST' => number_format($item->tax, 2), 
                    ];
                }else{
                    $reportData = [
                    'Merchant' => $item->merchant_name,
                    'Channel' => $item->channel_name,
                    'Channel Type' => $item->channel_type,
                    'Third Party Order Date' => !is_null($item->tp_order_date) ? Carbon::createFromFormat('Y-m-d H:i:s', $item->tp_order_date)->setTimezone($this->adminTz)->format('d/m/Y') : '',
                    'Order Completed Date' => !is_null($item->shipped_date) ? Carbon::createFromFormat('Y-m-d H:i:s', $item->shipped_date)->setTimezone($this->adminTz)->format('d/m/Y') : '',
                    'Order No' => $item->order_id,
                    'Third Party Order No' => $item->tp_order_code,
                    'Brand' => $item->brand_name,
                    'Hubwire SKU' => $item->hubwire_sku,
                    'Supplier SKU' => $item->sku_supplier_code,
                    'Product Name' => $item->product_name,
                    'Size' => $item->size,
                    'Color' => $item->color,
                    'Quantity' => $item->original_quantity,
                    'Currency' => $item->currency,
                    'Retail Price (Incl. GST)' => ($item->gst_reg == 1) ? number_format($item->unit_price, 2) : number_format($item->unit_price/(1+$item->tax_rate), 2),
                    'Retail Price (Excl. GST)' => number_format($item->unit_price/(1+$item->tax_rate), 2),
                    'Listing Price (Incl. GST)'=> ($item->gst_reg == 1) ? number_format($item_sale_price, 2) : number_format($item_sale_price/(1+$item->tax_rate), 2),
                    'Listing Price (Excl. GST)'=> number_format($item_sale_price/(1+$item->tax_rate), 2),
                    'Discounts' => number_format($discount * $item->original_quantity, 2), // sum of all the quantities
                    'Total Sales (Incl. GST)' => ($item->gst_reg == 1) ? number_format($item->sold_price * $item->original_quantity, 2): number_format($item->sold_price * $item->original_quantity, 2),
                    'Total Sales (Excl. GST)' => ($item->gst_reg == 1) ? number_format($soldAmountWithoutGst * $item->original_quantity, 2): number_format($item->sold_price * $item->original_quantity, 2),
                    'Consigment Number' => $item->consignment_no,
                    'ARC Item Status' => $item->status,
                    'Third Party Item Status' => $item->item_status,
                    ];
                }

                if ($tab != config('globals.tp_report_tab_type.PENDING_PAYMENT_TP')) {
                    $reportData['Net Payout'] = $item->net_payout;
                    $reportData['Channel Fees'] = $item->channel_fees;
                }

                $data[] = $reportData;
            }

            $response['success'] = true;
            $response['data'] = $data;
        }
        else {
            $response['success'] = false;
            $response['message'] = 'There are no records to be exported for the selected option.';
        }

        return $response; 
    }

    public function getRemarks($id){
        $remarks = ThirdPartyReportRemark::where('tp_report_id', $id)->orderBy('id', 'desc')->get();
        foreach($remarks as $remark){
            if($remark->added_by != 0){
                $user = User::where('id', $remark->added_by)->select('first_name', 'last_name')->first();
                $fullname = array($user->first_name, $user->last_name);
                $fullname = implode(' ', $fullname);
            }else{
                $fullname = 'System';
            }
            $remark->user = $fullname;
        }

        return $remarks;
    }

    public function resolveRemark($remarkId){
        $remark = ThirdPartyReportRemark::where('id', $remarkId)->firstOrFail();
        $remark->resolve_status = 1;
        $remark->save();

        Activity::log('Remark ID '. $remarkId .' has been resolved.', $this->userId);

        return $remark;
    }

    public function getLogs($id){
        $logs = ThirdPartyReportLog::where('tp_report_id', $id)->orderBy('id', 'desc')->get();
        foreach($logs as $log){
            if($log->modified_by != 0){
                $user = User::where('id', $log->modified_by)->select('first_name', 'last_name')->first();
                $fullname = array($user->first_name, $user->last_name);
                $fullname = implode(' ', $fullname);
            }else{
                $fullname = 'System';
            }
            $log->user = $fullname;
        }

        return $logs;
    }

    public function delete($id)
    {
        $thirdPartyReport = $this->model->find($id);
        if ($thirdPartyReport->status =='Not Found' || $thirdPartyReport->status =='Unverified') {
            $delete = $this->model->destroy($id);
        }else{
            $delete = " Third Party Report(".$id.") status are Verified or Completed";
        }
        
        return $delete;
    }

    public function discardChecking($option)
    {
        return ThirdPartyReport::where('status', $option)->get()->pluck('id');
    }

    public function generateReport($data) {
        dispatch( new GenerateReport($data) );
    }

}
