<?php
return [
    'webhook_events'=> [
        'sales/created',
        'sales/updated',
        'product/created',
        'product/updated',
        'sku/created',
        'sku/updated',
        'channel_sku/created',
        'channel_sku/updated',
        'channel_sku/quantity_changed',
        'media/created',
        'media/deleted',
        'media/updated',
        'delivery_order/created',
        'delivery_order/received',
        'delivery_order/deleted',
        'brand/created',
        'brand/updated',
        'sale_item/returned',
        'sale_item/updated',
        'sale_item/deleted',
        'procurement/created',
        'procurement/updated'
    ],
    'response' => [
        'limit' => 250
    ]

];
