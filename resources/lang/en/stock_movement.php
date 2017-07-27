<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SKU Stock Movements
    |--------------------------------------------------------------------------
    */

    // will only be created after received
    'created'               => '[+:quantity] SKU :skuId (:hubwireSku) created (Batch :batchId) into warehouse :channelId (:channelName).',
    'restocked'	            => '[+:quantity] SKU :skuId (:hubwireSku) has been restocked (Batch :batchId) into channel :channelId (:channelName).',

    'rejected'              => '[-:quantity] SKU :skuId (:hubwireSku) has been rejected (Reject :rejectId) from channel :channelId (:channelName) due to :reason.',

    // stock transfer (delivery order)
    'pre_transfer'          => ':quantity SKU :skuId (:hubwireSku) are in transit (Stock Transfer :doId) from channel :originatingChannelId (:originatingChannelName) to channel :targetChannelId (:targetChannelName).',
    'post_transfer'         => ':quantity SKU :skuId (:hubwireSku) has been transfered (Stock Transfer :doId) from channel :originatingChannelId (:originatingChannelName) to channel :targetChannelId (:targetChannelName).',

    // orders
    'sold'                  => ':quantity SKU :skuId (:hubwireSku) has been sold (Order :orderId).',
    'order_restocked'       => '[+:quantity] SKU :skuId (:hubwireSku) from order :orderId has been restocked (Return :returnId).',
    // rejecting an order item's return does not remove quantity from the system since it has been removed when the order came in
    // it simply does not add the quantity back into the system
    // thus, no stock movement
    'order_rejected'        => ':quantity SKU :skuId (:hubwireSku) from order :orderId has been rejected (Return :returnId) due to :reason.',
    // reserved quantity does not affect channel sku quantity (no stock movement)
    // when orders are created, quantity will be deducted from our system and added into the reserved quantity,
    // once SHIPPED, the reserved quantity will become 0
    'reserved'              => ':quantity SKU :skuId (:hubwireSku) has been reserved for order :orderId.',
    // in the inventory report, the END number shows: stock-in-hand (includes reserved stocks as it has not been shipped) + stock-in-transit
    // once item is shipped (fulfilled), it will no longer be our sstock in hand
    'reserved_fulfilled'    => '[-:quantity] SKU :skuId (:hubwireSku) fulfilled for order :orderId.',
    // these does not have any stock movement, only moved when restocked/rejected
    // 'cancelled'             => ':quantity SKU :skuId (:hubwireSku) has been cancelled in order :orderId.',
    // 'returned'              => ':quantity SKU :skuId (:hubwireSku) has been returned in order :orderId.',

    // others
    'stock_correction'      => '[:quantityDifference] Quantity of SKU :skuId (:hubwireSku) has been changed from :oldQuantity to :newQuantity during stock correction (remark: :remark).',
    'unknown'               => '[:quantityDifference] Quantity of SKU :skuId (:hubwireSku) has been changed from :oldQuantity to :newQuantity for unknown reason.',
    'missing'               => '[:quantityDifference] Quantity of SKU :skuId (:hubwireSku) has been changed from :oldQuantity to :newQuantity on a missing :refTable (ID :refId).'
];
