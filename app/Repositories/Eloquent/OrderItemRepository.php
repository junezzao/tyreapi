<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Repository as Repository;

use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\Contract;
use App\Models\Admin\ChannelContract;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;

use Carbon\Carbon;
use DB;

class OrderItemRepository extends Repository
{
	protected $model;

    // Variables for fees calculation
    protected $chargeReturns, $gstReg, $channelId, $brandId, $dateTimeRange, $totalBrandOrderItems, $contract, $contractClass;

	protected $skipCriteria = true;

	public function __construct(OrderItem $model, $calulateContractFee = false)
    {
        parent::__construct();
        $this->model = $model;

        if ($calulateContractFee) {
            if (empty($this->model->order)) $this->model->load('order');
            if (empty($this->model->ref)) $this->model->load('ref');

            $this->brandId = $this->model->ref->product->brand_id;
            $this->checkIssuingCompanyGstRegistered();
        }
    }

    public function model()
    {
        return 'App\Models\Admin\OrderItem';
    }

    public function setModel($model) {
        $this->model = $model;
    }

    public function initialize() {
        if (empty($this->model->order)) $this->model->load('order');
        if (empty($this->model->ref)) $this->model->load('ref');

        $this->brandId = $this->model->ref->product->brand_id;
        $this->checkIssuingCompanyGstRegistered();
    }

    public function setDateTimeRange($dateTimeRange) {
        $this->dateTimeRange = $dateTimeRange;
    }

    public function setContractClass($contractClass) {
        $this->contractClass = $contractClass;
    }

    public function getItemBrandContract($contractId = null) {
        $with = ($this->contractClass == 'Contract') ? 'contract_rules' : 'channel_contract_rules';
        $shippedDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->model->order->shipped_date)->toDateString();
        $fullClassPath = "App\Models\Admin\\$this->contractClass";

        if (!is_null($contractId)) {
            $contract = $fullClassPath::with($with)->find($contractId);
        }
        else {
            $contract = $fullClassPath::with($with)->where('brand_id', '=', $this->brandId)
                                            ->where('merchant_id', '=', $this->model->merchant_id)
                                            ->where('start_date', '<=', $shippedDate)
                                            ->where(function ($query) use ($shippedDate) {
                                                $query->whereNull('end_date')
                                                        ->orWhere('end_date', '>=', $shippedDate);
                                            });

            if ($this->contractClass == 'ChannelContract') {
                $contract = $contract->where('channel_id', '=', $this->channelId);
            }

            $contract = $contract->first();
        }

        $this->contract = $contract;
    }

    // Must set the contractClass and dateTimeRange beforehand

    public function calculateFee($updateFees = true, &$totalBrandOrderItems = array()) {

        if (empty($this->contractClass) || empty($this->dateTimeRange)) {
            return array(
                'success'   => false,
                'error'     => "Contract class and/or date range not set."
            );
        }

        // Check if item is chargeable
        if (!$this->model->isChargeable()) {
            return array(
                'success'   => false,
                'error'     => "Item " . $this->model->id . " is neither verified nor returned. Order " . $this->model->order->id . " have to be shipped before channel fee is calculated."
            );
        }

        if ($this->contractClass == "ChannelContract") {
            $this->channelId =  $this->model->order->channel_id;
            $this->checkChannelChargeReturns($this->channelId);
        }
        else {
            $this->chargeReturns = true;
            $this->gstReg = true; // Always charge GST for hubwire fees as we are GST registered.
        }

        if ($this->model->status == 'Returned') {
            // Check if channel charge for returns
            if ($this->contractClass == "ChannelContract" && !$this->chargeReturns) {
                return array(
                    'success'   => false,
                    'error'     => "Item " . $this->model->id . " was returned. Channel " . $this->channelId . " does not charge for returns."
                );
            }
            else { // Check return reason for "Wrongly sent by FMHW"
                $this->model->load('returnLog');
                if (strcasecmp(trim($this->model->returnLog->remark), "Wrongly sent by FMHW") == 0) {
                    return array(
                        'success'   => false,
                        'error'     => "Item " . $this->model->id . " was returned with reason 'Wrongly sent by FMHW' and will not be charged."
                    );
                }
            }
        }

        // Get item's brand contract and check its existence
        if (empty($this->contract)) {
            $this->getItemBrandContract();
        }
        $contract = $this->contract;

        if (is_null($contract)) {
            return array(
                'success'   => false,
                'error'     => "The shipped date of item " . $this->model->id . " of brand " . $this->brandId . (($this->contractClass == "ChannelContract") ? (" in channel " . $this->channelId) : "") . " does not falls within any contract's dates."
            );
        }

        $gst = $this->gstReg ? config('globals.GST') : 1;
        // If calculating channel fees, $totalBrandOrderItems will store the total order items of the brand in the channel
        if ($this->contractClass == 'ChannelContract') {
            if (!empty($totalBrandOrderItems[$this->brandId][$this->channelId])) {
                $this->totalBrandOrderItems = $totalBrandOrderItems[$this->brandId][$this->channelId];
            }
            else {
                $this->totalBrandOrderItems = $totalBrandOrderItems[$this->brandId][$this->channelId] = $this->getBrandTotalBase('order_items.id')->count;
            }
        }
        else {
            if (!empty($totalBrandOrderItems[$this->brandId])) {
                $this->totalBrandOrderItems = $totalBrandOrderItems[$this->brandId];
            }
            else {
                $this->totalBrandOrderItems = $totalBrandOrderItems[$this->brandId] = $this->getBrandTotalBase('order_items.id')->count;
            }
        }

        // Check if minimum guarantee should be charged
        $chargeMG = $contract->start_charge_mg;
        if (!$chargeMG) {
            $chargeMG = $this->checkToChargeMinimumGuarantee($contract->start_date);
            $contract->start_charge_mg = $chargeMG;
            $contract->save();
        }

        // To store fees (excluding GST)
        // Minimum guarantee amount in DB is excluding GST
        // All fees are per order item
        // Minimum guarantee will only be charged from the first FULL month onwards
        $fees = array(
            'min_guarantee'     => (!empty($contract->guarantee) && $chargeMG) ? ($contract->guarantee / $this->totalBrandOrderItems) : 0.000,
            'compulsory_fee'    => 0.000, // fees selected as "always charge"
            'whichever_higher'  => array(0.000) // so that will not pass in empty array to max()
        );

        $with = ($this->contractClass == 'Contract') ? 'contract_rules' : 'channel_contract_rules';
        foreach ($contract->{$with} as $rule) {
            // Check all conditionals are empty
            if (($rule->base == "Order Count" || $rule->base == "Order Item Count")
                && (empty($rule->channels) && empty($rule->products)
                && empty($rule->categories) && $rule->operand != "Not Applicable")) {
                // Base value is not needed in this case (to improve performance)
            }
            else {
                // This base value is already excluding GST (unless the base is counts)
                $baseValue = $this->getBaseValue($rule->base);
//
                // Check if rule applies only if any of the conditionals are not empty.
                // If all is empty, the rule applies regardless, so no need checking (to improve performance)
                if (!empty($rule->channels) || !empty($rule->products)
                    || !empty($rule->categories) || $rule->operand != "Not Applicable") {

                    if (!$this->checkContractRuleApplicable($rule, $baseValue)) {
                        continue;
                    }
                }
            }

            $fee = 0.000;

            if ($rule->type == 'Fixed Rate') {
                $fee = $rule->type_amount;

                // Only store fees in the first order item of the brand
                // E.g. Order has 3 order items of the same brand, only store fees for the first item
                if ($rule->base == 'Order Count') {
                    $this->model = $this->getFirstItemOfOrderByBrand();
                }
            }
            else if ($rule->type == 'Percentage') {
                switch ($rule->base) {
                    case "Retail Price":
                    case "Listing Price":
                    case "Sold Price":
                        $fee = $baseValue;
                        break;

                    case "Total Sales Retail Price":
                    case "Total Sales Listing Price":
                    case "Total Sales Sold Price":
                        $fee = floatval($baseValue / $this->totalBrandOrderItems);
                        break;

                    // these two bases cannot be of percentage type
                    case "Order Count":
                    case "Order Item Count":
                    default:
                        break;
                }

                if ($fee > 0) {
                    if ($rule->operand == 'Difference')
                        $fee = ($fee - $rule->min_amount) * floatval($rule->type_amount / 100);
                    else
                        $fee = $fee * floatval($rule->type_amount / 100);
                }
            }

            // fixed_charge == 0 (charge whichever higher)
            // fixed_charge == 1 (always charge)
            if ($rule->fixed_charge == 0) {
                $fees['whichever_higher'][] = $fee;
            }
            else {
                $fees['compulsory_fee'] += $fee;
            }
        }

        // $contract->min_guarantee == 1 (Charge rule only if exceed minimum guarantee)
        // $contract->min_guarantee == 0 (Charge on top of min_guarantee)
        $totalItemFee = $fees['compulsory_fee'] + max($fees['whichever_higher']);

        if ($updateFees) {
            if ($this->contractClass == 'Contract') {
                $this->model->hw_fee = ($totalItemFee > 0) ? number_format($totalItemFee * $gst, 3) : 0.000;
                $this->model->min_guarantee = ($fees['min_guarantee'] > 0) ? number_format($fees['min_guarantee'] * $gst, 3) : 0.000;
            }
            else {
                $this->model->channel_fee = ($totalItemFee > 0) ? number_format($totalItemFee * $gst, 3) : 0.000;
                $this->model->channel_mg = ($fees['min_guarantee'] > 0) ? number_format($fees['min_guarantee'] * $gst, 3) : 0.000;
            }

            $this->model->save();

            return array(
                'success'   => true,
                'response'  => "Updated " . (($this->contractClass == 'Contract') ? "hubwire" : "channel") . " fees for item " . $this->model->id . "."
            );
        }
        else {
            $return['success'] = true;
            $return['order'] = $this->model->order->id;
            $return['item'] = $this->model->id;
            $return['sale'] = $this->model->sold_price;
            $return['listing'] = $this->model->sale_price;
            $return['retail'] = $this->model->unit_price;
            if ($this->contractClass == 'Contract') {
                $return['type'] = 'hubwireFee';
                $return['fee'] = ($totalItemFee > 0) ? number_format($totalItemFee * $gst, 3) : 0.000;
                $return['mg'] = ($fees['min_guarantee'] > 0) ? number_format($fees['min_guarantee'] * $gst, 3) : 0.000;
                $return['channel'] = false;
            }
            else {
                $return['type'] = 'channelFee';
                $return['fee'] = ($totalItemFee > 0) ? number_format($totalItemFee * $gst, 3) : 0.000;
                $return['mg'] = ($fees['min_guarantee'] > 0) ? number_format($fees['min_guarantee'] * $gst, 3) : 0.000;
                $return['channel'] = true;
                //$return['channelId'] = $this->model->order->channel->id;
                $return['channelName'] = $this->model->order->channel->name;
            }
            return $return;
        }
    }

    public function checkChannelChargeReturns($channelId) {
        $channel = ChannelDetails::where('channel_id', '=', $channelId)
                            ->where('returns_chargable', '=', 1)
                            ->first();

        $this->chargeReturns = (is_null($channel)) ? false : true;
    }

    public function checkMerchantId($merchant_id) {
        if ($this->model->merchant_id == $merchant_id) {
           return true;
        }else{
            return false;
        }
    }

    public function checkIssuingCompanyGstRegistered() {
        if (empty($this->model->order->channel)) $this->model->order->load('channel');
        if (empty($this->model->order->channel->issuing_company)) $this->model->order->channel->load('issuing_company');

        $this->gstReg = $this->model->order->channel->getRelation('issuing_company')->gst_reg;
    }

    private function getBaseValue($base) {
        $gst = $this->gstReg ? config('globals.GST') : 1;
        $baseValue = 0.00;
        $columns = array(
            'Retail Price'              => 'unit_price',
            'Listing Price'             => 'sale_price',
            'Sold Price'                => 'sold_price',
            'Total Sales Retail Price'  => 'order_items.unit_price',
            'Total Sales Listing Price' => 'order_items.sale_price',
            'Total Sales Sold Price'    => 'order_items.sold_price',
            'Order Count'               => 'orders.id',
            'Order Item Count'          => 'order_items.id',
        );

        switch ($base) {
            case "Retail Price":
            case "Sold Price":
                // check to see if sold price is inclusive/exclusive gst
                if ( ($base == 'Sold Price' && $this->model->tax_inclusive == 0))
                    $baseValue = $this->model->{$columns[$base]};
                else
                    $baseValue = $this->model->{$columns[$base]} / $gst;
                break;

            case "Listing Price":
                $baseValue = (($this->model->sale_price > 0) ? $this->model->sale_price : $this->model->unit_price) / $gst;
                break;

            case "Total Sales Retail Price":
            case "Total Sales Listing Price":
            case "Total Sales Sold Price":
                // check to see if sold price is inclusive/exclusive gst
                if ( $base == 'Total Sales Sold Price' && $this->model->tax_inclusive == 0 )
                    $baseValue = $this->getBrandTotalBase($columns[$base])->sum;
                else
                    $baseValue = $this->getBrandTotalBase($columns[$base])->sum / $gst;
                break;
            case "Order Count":
            case "Order Item Count":
                $baseValue = $this->getBrandTotalBase($columns[$base])->count;
                break;

            default:
                break;
        }

        return $baseValue;
    }

    private function checkContractRuleApplicable($rule, $baseValue) {
        // Check against channels, products and categories
        if ((empty($rule->channels) || in_array($this->model->order->channel_id, json_decode($rule->channels)))
            && (empty($rule->products) || in_array($this->model->ref->product->id, json_decode($rule->products)))
            && (empty($rule->categories) || in_array($this->model->ref->product->category_id, json_decode($rule->categories)))) {

            // Check against operand
            if ($rule->operand == 'Not Applicable') return true;
            else if ($rule->operand == 'Above' && $baseValue >= $rule->min_amount) return true;
            else if ($rule->operand == 'Between' && $baseValue >= $rule->min_amount && $baseValue <= $rule->max_amount) return true;
            else if ($rule->operand == 'Difference' && $baseValue >= $rule->min_amount) return true;
        }

        return false;
    }

    private function getBrandTotalBase($column) {
        $select = (explode('.', $column)[1] == 'id') ? "count(DISTINCT $column) as count" : "sum($column) as sum, count(*) as count";

        $result = DB::table('order_items')
                        ->select(DB::raw($select))
                        ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                        ->leftjoin('return_log', 'return_log.order_item_id', '=', 'order_items.id')
                        ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                        ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                        ->leftjoin('products', 'products.id', '=', 'sku.product_id')

                        ->where('order_items.ref_type', '=', 'ChannelSKU')
                        ->whereBetween('orders.shipped_date', $this->dateTimeRange)
                        ->where('orders.status', '=', Order::$completedStatus)
                        ->where('products.brand_id', '=', $this->brandId);

        if ($this->chargeReturns || $this->contractClass == "Contract") {
            $result = $result->whereIn('order_items.status', ['Verified', 'Returned'])
                            ->where(function ($query) {
                                $query->whereNull('return_log.remark')
                                      ->orWhere('return_log.remark', '<>', 'Wrongly sent by FMHW');
                            });
        }
        else {
            $result = $result->where('order_items.status', '=', 'Verified');
        }

        if ($this->contractClass == 'ChannelContract') {
            $result = $result->where('orders.channel_id', '=', $this->channelId);
        }

        return $result->first();
    }

    private function checkToChargeMinimumGuarantee($contractStartDate) {
        $brandFirstOrderDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->getBrandFirstOrderDate(), 'UTC')->setTime(0, 0, 0);
        $contractStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $contractStartDate . ' 00:00:00', 'Asia/Kuala_Lumpur')->setTimezone('UTC');

        $startChargeDate = '';

        // If the brand's first order date is not 1, start charging from the next month of the first order date
        if ($brandFirstOrderDate->day != 1) {
            $startChargeDate = $brandFirstOrderDate->addMonth()->startOfMonth();
        }
        else {
            $startChargeDate = $brandFirstOrderDate;
        }

        $thisOrderDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->model->order->tp_order_date, 'UTC')->setTime(0, 0, 0);
        // If this order date is earlier than the date to start charging
        if ($thisOrderDate->lt($startChargeDate )) {
            return false;
        }

        return true;
    }

    private function getBrandFirstOrderDate() { // getBrandFirstOrderDate($contractStartDate)
        $items = DB::table('order_items')
                        ->select(DB::raw("min(orders.tp_order_date) as firstOrderDate"))
                        ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                        ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                        ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                        ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                        // ->where('orders.tp_order_date', '>=', ($contractStartDate . ' 00:00:00'))
                        ->where('products.brand_id', '=', $this->brandId);

        if ($this->contractClass == 'ChannelContract') {
            $items = $items->where('orders.channel_id', '=', $this->channelId);
        }

        return $items->first()->firstOrderDate;
    }

    // With brands taken into account
    // Order is already filtered by channel so no need to put in an extra where clause
    private function getFirstItemOfOrderByBrand() {
        $brandId = $this->brandId;
        $chargeReturns = $this->chargeReturns;

        $item = OrderItem::setEagerLoads([])
                            ->select('order_items.*')
                            ->leftjoin('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                            ->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                            ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                            ->where('products.brand_id', '=', $this->brandId)
                            ->where('order_items.order_id', '=', $this->model->order->id)
                            ->where('order_items.ref_type', '=', 'ChannelSKU');

         if ($chargeReturns) {
            $item = $item->leftjoin('return_log', 'return_log.order_item_id', '=', 'order_items.id')
                    ->whereIn('order_items.status', ['Verified', 'Returned'])
                    ->where(function ($query) {
                        $query->whereNull('return_log.remark')
                              ->orWhere('return_log.remark', '<>', 'Wrongly sent by FMHW');
                    });
        }
        else {
            $item = $item->where('order_items.status', '=', 'Verified');
        }

        $item = $item->orderBy('order_items.id', 'asc')
                        ->first();

        return $item;
    }
}
