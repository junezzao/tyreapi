<?php

$factory->define(Partner::class, function (Faker\Generator $faker) {
    return [
        'partner_name' => $faker->company,
        'partner_contact' => $faker->name,
        'partner_address' => $faker->address
    ];
});

$factory->define(Client::class, function (Faker\Generator $faker) {
    return [
        'client_name' => $faker->company,
        'client_contact_person' => $faker->name,
        'client_address' => $faker->address,
        'client_timezone' => $faker->timezone,
        'client_currency' => $faker->currencyCode
    ];
});

$factory->define(Channel::class, function (Faker\Generator $faker) {
    return [
        'channel_name' => $faker->company,
        'channel_web' => $faker->tld.'.'.$faker->domainName,
        'channel_address' => $faker->address,
        'channel_currency' => $faker->currencyCode,
        'channel_timezone' => $faker->timezone
    ];
});

$factory->define(Menu::class, function (Faker\Generator $faker) {
    return [];
});

$factory->define(OAuthClient::class, function (Faker\Generator $faker) {
    return [
        // 'id' => $faker->randomNumber(6),
        'secret' => $faker->sha1,
        'name' => $faker->name,
        'slug' => $faker->bothify('?#?#?#'),
    ];
});

$factory->define(DistributionCenter::class, function (Faker\Generator $faker) {
    return [
    ];
});

$factory->define(Brand::class, function (Faker\Generator $faker) {
    return [
        'brand_name' => $faker->sentence,
        'product_brand' => $faker->bothify('???##')
    ];
});


$factory->define(Product::class, function (Faker\Generator $faker) {
    return [
        'product_name' => $faker->sentence,
        'product_desc' => $faker->text
    ];
});

$factory->define(SKU::class, function (Faker\Generator $faker) {
    return [
        'sku_barcode' => $faker->ean13,
        'sku_weight' => $faker->randomNumber(3),
        'hubwire_sku' => $faker->ean13
    ];
});

$factory->define(SKUOption::class, function (Faker\Generator $faker) {
    return [
        'option_name' => 'Colour',
        'option_value' => $faker->safeColorName
    ];
});

$factory->define(SKUCombination::class, function (Faker\Generator $faker) {
    return [];
});

$factory->define(SKUTag::class, function (Faker\Generator $faker) {
    return [
        'tag_value' => $faker->word,
    ];
});

$factory->define(ChannelSKU::class, function (Faker\Generator $faker) {
    return [
        'channel_sku_quantity' => $faker->randomNumber(3),
        'channel_sku_active' => 1,
        'channel_sku_price' => $faker->randomNumber(2)
    ];
});

$factory->define(Sales::class, function (Faker\Generator $faker) {
    return [
        'payment_type' => 'ipay88',
        'sale_total' => $faker->randomNumber(2).'.'.$faker->randomNumber(2),
        'sale_shipping'=> $faker->randomNumber(2).'.'.$faker->randomNumber(2),
        'sale_status' => 'paid',
        'sale_address' => $faker->address,
        'sale_phone' => $faker->phoneNumber,
        'sale_recipient' => $faker->name,
        'sale_discount' => 0,
        'shipping_no' => $faker->ean8,
        'sale_postcode' => $faker->postcode,
        'sale_country' => $faker->country

    ];
});

$factory->define(SalesItem::class, function (Faker\Generator $faker) {
    $quantity = $faker->randomNumber(1);
    return [
       'item_quantity' => $quantity,
       'product_type' => 'ChannelSKU',
       'item_discount' => 0,
       'item_original_quantity'=>$quantity
    ];
});

$factory->define(Webhook::class, function (Faker\Generator $faker) {
    $quantity = $faker->randomNumber(1);
    return [
        'topic' => 'orders/created',
        'address'=> 'http://requestb.in/1de80jr1',
        'format'=> 'json'
    ];
});
