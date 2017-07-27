<?php
    /**
     *@apiDefine myHeader
     * @apiHeader {String} Authorization A string containing the token type followed by a space character and then the access token value you received.
     */

    /**
     * @api {post} /oauth/access_token OAuth
     * @apiDescription Hubwire API requires authentication - specifically requests made on behalf of a client. 
     * Authenticated requests require an access_token.
     * These tokens are unique to a client and should be stored securely.
     * Each access token will be expired in 1 day.
     *
     * @apiVersion 1.0.0
     * @apiName Oauth
     * @apiGroup Authentication
     *
     * @apiParam {Integer} client_id Your client ID.
     * @apiParam {String} client_secret Your client secret.
     * @apiParam {String} grant_type client_credentials is currently the only supported value.
     *
     * @apiSuccess {String} access_token OAuth access token for specified client.
     * @apiSuccess {String} token_type Type of OAuth access token.
     * @apiSuccess {Integer} expires_in Remaining lifetime of the access token in seconds.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "access_token": "LnJIH5ltiZDIMzwkGYlSAUvWakmOFy1kP9gzYGFM",
     *     "token_type": "Bearer",
     *     "expires_in": 86400
     *   }
     *
     * 
     * @apiExample {php} cURL Sample Code
     *   // Get cURL resource
     *   $curl = curl_init();
     *   curl_setopt_array($curl, array(
     *      CURLOPT_URL => 'http://sandbox-api.hubwire.com/1.0/oauth/access_token',
     *      CURLOPT_POST => 1,
     *      CURLOPT_POSTFIELDS => array(
     *         'client_id' => '853713992049',
     *         'client_secret' => '372e8f9fe7998aaff6a801k8035d19e92620f5gr',
     *         'grant_type' => 'client_credentials'
     *      ),
     *      CURLOPT_RETURNTRANSFER => 1
     *   ));
     *
     *   // Send the request & save response to $response
     *   $response = json_decode(curl_exec($curl), true);
     *
     *   // Close request to clear up some resources
     *   curl_close($curl);    
     *
     *   echo '<pre>';
     *   print_r($response);
     *   echo '</pre>'; 
     *
     */

    /**
     * @api {get} /products/:product_id Get single product information
     * @apiDescription Get information about a product. 
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetProduct
     * @apiGroup Products
     *
     * @apiParam {Integer} product_id The product ID.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} product Product Array.
     * @apiSuccess {Integer} product.id Product ID.
     * @apiSuccess {String} product.name Name of product.
     * @apiSuccess {String} product.description Description of product.
     * @apiSuccess {String} product.sub_description Sub-description of product.
     * @apiSuccess {Number} product.quantity Total quantity of product.
     * @apiSuccess {Array} product.tags Tags of product.
     * @apiSuccess {Boolean} product.active Status of product. 0 = inactive, 1 = active.
     * @apiSuccess product.category Category of product.
     * @apiSuccess {Integer} product.category.id Cateogry ID.
     * @apiSuccess {String} product.category.name Name of category.
     * @apiSuccess product.brand Brand of product.
     * @apiSuccess {Integer} product.brand.id Brand ID.
     * @apiSuccess {String} product.brand.code Code of brand.
     * @apiSuccess {String} product.brand.name Name of brand.
     * @apiSuccess {Array} product.sku List of SKUs of the product.
     * @apiSuccess {Integer} product.sku.id SKU ID.
     * @apiSuccess {String} product.sku.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} product.sku.product_id Product ID.
     * @apiSuccess {Number} product.sku.weight Weight of SKU.
     * @apiSuccess {Integer} product.sku.quantity Stock quantity of SKU.
     * @apiSuccess {Boolean} product.sku.active Status of SKU. 0 = inactive, 1 = active.
     * @apiSuccess {Number} product.sku.retail_price Retail / Original price of SKU
     * @apiSuccess {Number} product.sku.sale_price Listing price of SKU. 0 means no listing price specified.
     * @apiSuccess {String} product.sku.warehouse_coordinate Warehouse coordinates where SKU stocks are located.
     * @apiSuccess product.sku.options List of options available for SKU, for example Size, Colour etc.
     * @apiSuccess {Date} product.sku.created_at SKU created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} product.sku.updated_at SKU last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Array} product.media List of media of the product.
     * @apiSuccess {Integer} product.media.id Media ID.
     * @apiSuccess {String} product.media.url URL path for the media.
     * @apiSuccess {String} product.media.extension File extension of media.
     * @apiSuccess {Integer} product.media.order Position order of media.
     * @apiSuccess {Integer} product.default_media Default media id of product.
     * @apiSuccess {Date} product.created_at Product created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} product.updated_at Product last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess product.custom_fields Custom fields of product, if any.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "product": {
     *       "id": 23,
     *       "name": "Giselle Maxi Skirt",
     *       "description": "A flowy design with a soft touch, making it a unique addition to your casual repertoire!",
     *       "sub_description": "Style up or down with this simple maxi skirt.",
     *       "quantity" : 0,
     *       "tags": [
     *         "Woman",
     *         "Skirts",
     *         "Black"
     *       ],
     *       "active": 1, 
     *       "category": {
     *         "id": "449",
     *         "name": "Fashion/Women/Clothing/Skirts/Midi",
     *       },     
     *       "brand": {
     *         "id": "5",
     *         "code": "ZMC",
     *         "name": "ZIMZ CO.",
     *       },
     *       "sku": [
     *         {
     *           "id": 31,
     *           "hubwire_sku": "K-005-WH-M",
     *           "product_id": 23,
     *           "weight": 200,
     *           "quantity": 5,
     *           "active": 1,
     *           "retail_price": 139,
     *           "sale_price": 0,
     *           "warehouse_coordinate": "B-2-10",
     *           "options": {
     *             "Size": "M",
     *             "Colour": "black"
     *           },
     *           "created_at": "2015-08-19 02:50:15",
     *           "updated_at": "2015-08-19 02:50:15"
     *         },
     *       ],
     *       "media": [
     *         {
     *           "id": 3423,
     *           "url": "https://s3-ap-southeast-1.amazonaws.com/hubwire.com/56877589963ea_800x1148",
     *           "extension": ".jpg",
     *           "order": "0"
     *         },
     *       ],
     *       "default_media": "3423",
     *       "created_at": "2014-10-20 03:31:02",
     *       "updated_at": "2015-08-19 02:48:15",
     *       "custom_fields": {
     *         "Material": "Cotton"
     *       }
     *     }
     *   }
     *
     * @apiExample {php} cURL Sample Code
     *   // Get cURL resource
     *   $curl = curl_init();
     *   curl_setopt_array($curl, array(
     *      CURLOPT_URL => 'http://sandbox-api.hubwire.com/1.0/products/22',
     *      CURLOPT_HTTPHEADER => array(
     *         'Authorization: Bearer vADy9SFRR18em41BrJ3YkJY09qnIzJIn95U5yRWi'
     *      ),
     *      CURLOPT_RETURNTRANSFER => 1
     *   ));
     *
     *   // Send the request & save response to $response
     *   $response = json_decode(curl_exec($curl), true);
     *
     *   // Close request to clear up some resources
     *   curl_close($curl);    
     *
     *   echo '<pre>';
     *   print_r($response);
     *   echo '</pre>'; 
     */
    
    /**
     * @api {get} /products Get a list of products
     * @apiDescription Get information about a list of products. All parameters are optional. 
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetProducts
     * @apiGroup Products
     *
     * @apiParam {Integer} limit Amount of results, default is 50, maximum is 250.
     * @apiParam {Integer} start Restrict results to after the specified index.
     * @apiParam {Date} created_at Show products created after date (format: YYYY-MM-DD).
     * @apiParam {Date} updated_at Show products last updated after date (format: YYYY-MM-DD).
     * @apiParam {Integer} sinceid Restrict results to after the specified product ID.
     * @apiParam {Boolean} changed Filter by product changed. 0 = no changes, 1 = with changes.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Integer} start Results restricted to after this specified index.
     * @apiSuccess {Integer} limit Current amount of results.
     * @apiSuccess {Integer} total Total amount of results.
     * @apiSuccess {Array} products List of products.
     * @apiSuccess {Integer} products.id Product ID.
     * @apiSuccess {String} products.name Name of product.
     * @apiSuccess {String} products.description Description of product.
     * @apiSuccess {String} products.sub_description Sub-description of product.
     * @apiSuccess {Number} products.quantity Total quantity of product.
     * @apiSuccess {Array} products.tags Tags of product.
     * @apiSuccess {Boolean} products.active Status of product. 0 = inactive, 1 = active.
     * @apiSuccess products.category Category of product.
     * @apiSuccess {Integer} products.category.id Cateogry ID.
     * @apiSuccess {String} products.category.name Name of category.
     * @apiSuccess products.brand Brand of product.
     * @apiSuccess {Integer} products.brand.id Brand ID.
     * @apiSuccess {String} products.brand.code Code of brand.
     * @apiSuccess {String} products.brand.name Name of brand.
     * @apiSuccess {Array} products.sku List of SKUs of the product.
     * @apiSuccess {Integer} products.sku.id SKU ID.
     * @apiSuccess {String} products.sku.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} products.sku.product_id Product ID.
     * @apiSuccess {Number} products.sku.weight Weight of SKU.
     * @apiSuccess {Integer} products.sku.quantity Stock quantity of SKU.
     * @apiSuccess {Boolean} products.sku.active Status of SKU. 0 = inactive, 1 = active.
     * @apiSuccess {Number} products.sku.retail_price Retail / Original price of SKU
     * @apiSuccess {Number} products.sku.sale_price Listing price of SKU. 0 means no listing price specified.
     * @apiSuccess {String} products.sku.warehouse_coordinate Warehouse coordinates where SKU stocks are located.
     * @apiSuccess products.sku.options List of options available for SKU, for example Size, Colour etc.
     * @apiSuccess {Date} products.sku.created_at SKU created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} products.sku.updated_at SKU last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Array} products.media List of media of the product.
     * @apiSuccess {Integer} products.media.id Media ID.
     * @apiSuccess {String} products.media.url URL path for the media.
     * @apiSuccess {String} product.media.extension File extension of media.
     * @apiSuccess {Integer} products.media.order Position order of media.
     * @apiSuccess {Integer} products.default_media Default media id of product.
     * @apiSuccess {Date} products.created_at Product created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} products.updated_at Product last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess products.custom_fields Custom fields of product, if any.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "start": 0,
     *     "limit": 50,
     *     "total": 79,
     *     "products": [
     *       {
     *         "id": 23,
     *         "name": "Giselle Maxi Skirt",
     *         "description": "A flowy design with a soft touch, making it a unique addition to your casual repertoire!",
     *         "sub_description": "Style up or down with this simple maxi skirt.",
     *         "quantity" : 0,
     *         "tags": [
     *           "Woman",
     *           "Skirts",
     *           "Black"
     *         ],
     *         "active": 1, 
     *         "category": {
     *           "id": "449",
     *           "name": "Fashion/Women/Clothing/Skirts/Midi",
     *         },    
     *         "brand": {
     *           "id": "5",
     *           "code": "ZMC",
     *           "name": "ZIMZ CO."
     *         },
     *         "sku": [
     *           {
     *             "id": 31,
     *             "hubwire_sku": "K-005-WH-M",
     *             "product_id": 23,
     *             "weight": 200,
     *             "quantity": 5,
     *             "active": 1,
     *             "retail_price": 139,
     *             "sale_price": 0,
     *             "warehouse_coordinate": "B-2-10",
     *             "options": {
     *               "Size": "M",
     *               "Colour": "black"
     *             },
     *             "created_at": "2015-08-19 02:50:15",
     *             "updated_at": "2015-08-19 02:50:15"
     *           },
     *         ],
     *         "media": [
     *           {
     *             "id": 3423,
     *             "url": "https://s3-ap-southeast-1.amazonaws.com/hubwire.com/56877589963ea_800x1148",
     *             "extension": ".jpg",
     *             "order": "0"
     *           },
     *         ],
     *         "default_media": "3423",
     *         "created_at": "2014-10-20 03:31:02",
     *         "updated_at": "2015-08-19 02:48:15",
     *         "custom_fields": {
     *           "Material": "Cotton"
     *         }
     *       }
     *     ]
     *   }
     *
     */

    /**
     * @api {get} /sku/:sku_id Get single SKU information
     * @apiDescription Get information about a SKU. 
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetSKU
     * @apiGroup SKUs
     *
     * @apiParam {Integer} sku_id The SKU ID.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} sku SKU Array.
     * @apiSuccess {Integer} sku.id SKU ID.
     * @apiSuccess {String} sku.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} sku.product_id Product ID.
     * @apiSuccess {Number} sku.weight Weight of SKU.
     * @apiSuccess {Number} sku.quantity Stock quantity of SKU.
     * @apiSuccess {Boolean} sku.active Status of SKU. 0 = inactive, 1 = active.
     * @apiSuccess {Number} sku.retail_price Retail / Original price of SKU.
     * @apiSuccess {Number} sku.sale_price Listing price of SKU. 0 means no listing price specified.
     * @apiSuccess {String} sku.warehouse_coordinate Warehouse coordinates where SKU stocks are located.
     * @apiSuccess sku.options List of options available for SKU, for example Size, Colour etc.
     * @apiSuccess {Date} sku.created_at Created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} sku.updated_at Last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "sku": {
     *       "id": 31,
     *       "hubwire_sku": "K-005-WH-M",
     *       "product_id": 23,
     *       "weight": 200,
     *       "quantity": 5,
     *       "active": 1,
     *       "retail_price": 139,
     *       "sale_price": 0,
     *       "warehouse_coordinate": "B-2-10",
     *       "options": {
     *         "Size": "M",
     *         "Colour": "black"
     *       },
     *       "created_at": "2015-08-19 02:50:15",
     *       "updated_at": "2015-08-19 02:50:15"
     *     }
     *   } 
     *
     */

    /**
     * @api {get} /sales/:sale_id Get single order information
     * @apiDescription Get information about an order. 
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetOrder
     * @apiGroup Orders
     *
     * @apiParam {Integer} sale_id The order ID.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} order Order Array.
     * @apiSuccess {Integer} order.id Order ID.
     * @apiSuccess {String} order.order_number Your order number for reference.
     * @apiSuccess {Date} order.order_date Your order date for reference in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Number} order.total_price Sum of all prices of all items in the order, taxes and shipping fee included, after discounts have been applied.
     * @apiSuccess {Number} order.total_discount Total amount of discounts, including Hubwire and Third Party discount.
     * @apiSuccess {Number} order.shipping_fee Total shipping amount applied.
     * @apiSuccess {String} order.currency Three-letter currency code used for payment.
     * @apiSuccess {String} order.payment_type Type of payment processing method.
     * @apiSuccess {String} order.status Status of the order.
     * @apiSuccess {Boolean} order.cancelled_status Indicates whether order has been cancelled. 0 = not cancelled, 1 = cancelled
     * @apiSuccess {Date} order.created_at Order created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} order.updated_at Order last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess order.shipping_info Shipping information of the order.
     * @apiSuccess {String} order.shipping_info.recipient Name of recipient.
     * @apiSuccess {String} order.shipping_info.phone Phone number of recipient.
     * @apiSuccess {String} order.shipping_info.tracking_no Tracking number provided by shipping courier.
     * @apiSuccess {String} order.shipping_info.shipping_provider Name of shipping courier.
     * @apiSuccess {String} order.shipping_info.address_1 Street address.
     * @apiSuccess {String} order.shipping_info.address_2 Optional additional field for street address.
     * @apiSuccess {String} order.shipping_info.city City of shipping address.
     * @apiSuccess {String} order.shipping_info.postcode Postal code. of shipping address
     * @apiSuccess {String} order.shipping_info.state State of shipping address.
     * @apiSuccess {String} order.shipping_info.country Country of shipping address.
     * @apiSuccess {Array} order.items List of items of the order.
     * @apiSuccess {Integer} order.items.id Item ID.
     * @apiSuccess {Integer} order.items.sku_id SKU ID.
     * @apiSuccess {String} order.items.product_name Name of product.
     * @apiSuccess {String} order.items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} order.items.tp_item_id Your item reference ID for finance and reporting purpose. Mandatory.
     * @apiSuccess {Integer} order.items.quantity Number of items purchased.
     * @apiSuccess {Number} order.items.retail_price Retail price of item.
     * @apiSuccess {Number} order.items.sale_price Listing price of item, after Hubwire discount has been applied. 0 means no listing price specified.
     * @apiSuccess {Number} order.items.price Paid price by customer, after Hubwire and Third Party discounts have been applied.
     * @apiSuccess {Number} order.items.hw_discount Hubwire discount amount applied to this line item.
     * @apiSuccess {Number} order.items.discount Third Party discount amount applied to this line item.
     * @apiSuccess {Number} order.items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} order.items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {String} order.items.status Status of item. Values: Picking, Picked, Verified, Out of Stock, Cancelled, Returned.
     * @apiSuccess order.customer Customer information of the order.
     * @apiSuccess {Integer} order.customer.id Customer ID.
     * @apiSuccess {String} order.customer.name Customer's name.
     * @apiSuccess {String} order.customer.email Customer's email address.
     * @apiSuccess {String} order.customer.phone Customer's phone number.
     * @apiSuccess {Date} order.customer.created_at Customer created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} order.customer.updated_at Customer last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "order":{
     *       "id": 1020,
     *       "order_number": "#6730",
     *       "order_date": "2016-05-11 02:20:00",
     *       "total_price": 145,
     *       "total_discount": 13.90,
     *       "shipping_fee": 6,
     *       "currency": "MYR",
     *       "payment_type": "paypal",
     *       "status": "paid",
     *       "cancelled_status": 0,
     *       "created_at": "2016-05-11 02:22:50",
     *       "updated_at": "2016-05-11 02:22:50",
     *       "shipping_info": {    
     *         "recipient": "Amy Shoon",
     *         "phone": "0139855381",
     *         "tracking_no": "EH144050790MY",
     *         "shipping_provider": "PosLaju",
     *         "address_1": "Unit 17-7 Jalan PJU 1/41",
     *         "address_2": "Dataran Prima",
     *         "city": "Petaling Jaya",
     *         "postcode": 47301,
     *         "state": "Selangor",
     *         "country": "Malaysia"
     *       },
     *       "items": [
     *         {
     *           "id": 16912,
     *           "sku_id": 31,
     *           "product_name": "Giselle Maxi Skirt",
     *           "hubwire_sku": "K-005-WH-M",
     *           "tp_item_id": "2037",
     *           "quantity": 1,
     *           "retail_price": 139,
     *           "sale_price": 0,
     *           "price": 125.10,
     *           "hw_discount": 0,
     *           "discount": 13.90,
     *           "tax": 7.51,
     *           "tax_inclusive": 1,
     *           "status": "Verified"
     *         }
     *       ],
     *       "customer": {
     *         "id": 1432,
     *         "name": "Joseph Adam",
     *         "email": "joseph.adam@gmail.com",
     *         "phone": "0197333126",
     *         "created_at": "2016-05-11 02:22:50",
     *         "updated_at": "2016-05-11 02:22:50",
     *       } 
     *     }
     *   }
     *
     */

    /**
     * @api {get} /sales Get a list of orders
     * @apiDescription Get information about a list of orders. All parameters are optional. 
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetOrders
     * @apiGroup Orders
     *
     * @apiParam {Integer} limit Amount of results, default is 50, maximum is 250.
     * @apiParam {Integer} start Restrict results to after the specified index.
     * @apiParam {String} status Filter by order status.
     * @apiParam {Date} created_at Show orders created after date (format: YYYY-MM-DD).
     * @apiParam {Date} updated_at Show orders last updated after date (format: YYYY-MM-DD).
     * @apiParam {Integer} sinceid Restrict results to after the specified order ID.
     * @apiParam {Boolean} changed Filter by update of order. 0 = no update, 1 = with update.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Integer} start Results restricted to after this specified index.
     * @apiSuccess {Integer} limit Current amount of results.
     * @apiSuccess {Integer} total Total amount of results.
     * @apiSuccess {Array} orders List of orders.
     * @apiSuccess {Integer} orders.id Order ID.
     * @apiSuccess {String} orders.order_number Your order number for reference.
     * @apiSuccess {Date} orders.order_date Your order date for reference in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Number} orders.total_price Sum of all prices of all items in the order, taxes and shipping fee included, after discounts have been applied.
     * @apiSuccess {Number} orders.total_discount Total amount of discounts, including Hubwire and Third Party discount.
     * @apiSuccess {Number} orders.shipping_fee Total shipping amount applied.
     * @apiSuccess {String} orders.currency Three-letter currency code used for payment.
     * @apiSuccess {String} orders.payment_type Type of payment processing method.
     * @apiSuccess {String} orders.status Status of the order.
     * @apiSuccess {Boolean} orders.cancelled_status Indicates whether order has been cancelled. 0 = not cancelled, 1 = cancelled.
     * @apiSuccess {Date} orders.created_at Order created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} orders.updated_at Order last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess orders.shipping_info Shipping information of the order.
     * @apiSuccess {String} orders.shipping_info.recipient Name of recipient.
     * @apiSuccess {String} orders.shipping_info.phone Phone number of recipient.
     * @apiSuccess {String} orders.shipping_info.tracking_no Tracking number provided by shipping courier.
     * @apiSuccess {String} orders.shipping_info.shipping_provider Name of shipping courier.
     * @apiSuccess {String} orders.shipping_info.address_1 Street address.
     * @apiSuccess {String} orders.shipping_info.address_2 Optional additional field for street address.
     * @apiSuccess {String} orders.shipping_info.city City of shipping address.
     * @apiSuccess {String} orders.shipping_info.postcode Postal code. of shipping address
     * @apiSuccess {String} orders.shipping_info.state State of shipping address.
     * @apiSuccess {String} orders.shipping_info.country Country of shipping address.
     * @apiSuccess {Array} orders.items List of items of the order.
     * @apiSuccess {Integer} orders.items.id Item ID.
     * @apiSuccess {Integer} orders.items.sku_id SKU ID.
     * @apiSuccess {String} orders.items.product_name Name of product.
     * @apiSuccess {String} orders.items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} orders.items.tp_item_id Your item reference ID for finance and reporting purpose. Mandatory.
     * @apiSuccess {Integer} orders.items.quantity Number of items purchased.
     * @apiSuccess {Integer} orders.items.retail_price Retail price of item.
     * @apiSuccess {Number} orders.items.sale_price Listing price of item, after Hubwire discount has been applied. 0 means no listing price specified.
     * @apiSuccess {Number} orders.items.price Paid price by customer, after Hubwire and Third Party discounts have been applied.
     * @apiSuccess {Number} orders.items.hw_discount Hubwire discount amount applied to this line item.
     * @apiSuccess {Number} orders.items.discount Third Party discount amount applied to this line item.
     * @apiSuccess {Number} orders.items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} orders.items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {String} orders.items.status Status of item. Values: Picking, Picked, Verified, Out of Stock, Cancelled, Returned.
     * @apiSuccess orders.customer Customer information of the order.
     * @apiSuccess {Integer} orders.customer.id Customer ID.
     * @apiSuccess {String} orders.customer.name Customer's name.
     * @apiSuccess {String} orders.customer.email Customer's email address.
     * @apiSuccess {String} orders.customer.phone Customer's phone number.
     * @apiSuccess {Date} orders.customer.created_at Customer created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} orders.customer.updated_at Customer last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "start": 0,
     *     "limit": 50,
     *     "total": 79,
     *     "orders": [
     *       {
     *         "id": 1020,
     *         "order_number": "#6730",
     *         "order_date": "2016-05-11 02:20:00",
     *         "total_price": 145,
     *         "total_discount": 13.90,
     *         "shipping_fee": 6,
     *         "currency": "MYR",
     *         "payment_type": "paypal",
     *         "status": "paid",
     *         "cancelled_status": 0,
     *         "created_at": "2016-05-11 02:22:50",
     *         "updated_at": "2016-05-11 02:22:50",
     *         "shipping_info": {    
     *           "recipient": "Amy Shoon",
     *           "phone": "0139855381",
     *           "tracking_no": "EH144050790MY",
     *           "shipping_provider": "PosLaju",
     *           "address_1": "Unit 17-7 Jalan PJU 1/41",
     *           "address_2": "Dataran Prima",
     *           "city": "Petaling Jaya",
     *           "postcode": 47301,
     *           "state": "Selangor",
     *           "country": "Malaysia"
     *         },
     *         "items": [
     *           {
     *             "id": 16912,
     *             "sku_id": 31,
     *             "product_name": "Giselle Maxi Skirt",
     *             "hubwire_sku": "K-005-WH-M",
     *             "tp_item_id": "2037",
     *             "quantity": 1,
     *             "retail_price": 139,
     *             "sale_price": 0,
     *             "price": 125.10,
     *             "hw_discount": 0,
     *             "discount": 13.90,
     *             "tax": 7.51,
     *             "tax_inclusive": 1,
     *             "status": "Verified"
     *           }
     *         ],
     *         "customer": {
     *           "id": 1432,
     *           "name": "Joseph Adam",
     *           "email": "joseph.adam@gmail.com",
     *           "phone": "0197333126",
     *           "created_at": "2016-05-11 02:22:50",
     *           "updated_at": "2016-05-11 02:22:50",
     *         } 
     *       }
     *     ]
     *   }
     *
     */

    /**
     * @api {post} /sales Create an order
     * @apiDescription Create a new order
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName CreateOrder
     * @apiGroup Orders
     *
     * @apiParam {String} order_number Your order number for reference.
     * @apiParam {Date} order_date Your order date for reference in YYYY-MM-DD H:i:s format.
     * @apiParam {Number} shipping_fee Total shipping amount applied.
     * @apiParam {String} currency Three-letter currency code used for payment.
     * @apiParam {String} payment_type Type of payment processing method.
     * @apiParam {String} status Status of the order. Only expected value: paid.
     * @apiParam shipping_info Shipping information of the order.
     * @apiParam {String} shipping_info.recipient Name of recipient.
     * @apiParam {String} shipping_info.phone Phone number of recipient.
     * @apiParam {String} shipping_info.tracking_no Tracking number provided by shipping courier.
     * @apiParam {String} shipping_info.shipping_provider Name of shipping courier.
     * @apiParam {String} shipping_info.address_1 Street address.
     * @apiParam {String} shipping_info.address_2 Optional additional field for street address.
     * @apiParam {String} shipping_info.city City of shipping address.
     * @apiParam {String} shipping_info.postcode Postal code. of shipping address
     * @apiParam {String} shipping_info.state State of shipping address.
     * @apiParam {String} shipping_info.country Country of shipping address.
     * @apiParam {Array} items List of items of the order.
     * @apiParam {Integer} items.sku_id SKU ID.
     * @apiParam {Integer} items.tp_item_id Your item reference ID for finance and reporting purpose. Mandatory.
     * @apiParam {Integer} items.quantity Number of items purchased.
     * @apiParam {Integer} items.price Paid price by customer, after Hubwire and Third Party discounts have been applied, per single quantity.
     * @apiParam {Number} items.discount Third Party discount amount applied to this line item, per single quantity.
     * @apiParam {Number} items.tax Amount of tax to be charged, per single quantity.
     * @apiParam {Boolean} items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiParam customer Customer information of the order.
     * @apiParam {String} customer.name Customer's name.
     * @apiParam {String} customer.email Customer's email address.
     * @apiParam {String} customer.phone Customer's phone number.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess order Order info.
     * @apiSuccess {Integer} order.order_id Order ID.
     * @apiSuccess {String} order.success Status of response.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "order": {
     *       "order_id": 1020,
     *       "success": true
     *     }
     *   }
     *
     */

    /**
     * @api {get} /sales/:sale_id/items Get all items of an order
     * @apiDescription Get information about all items of an order.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetOrderItems
     * @apiGroup OrdersItems
     *
     * @apiParam {Integer} sale_id The order ID.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} items List of items of the order.
     * @apiSuccess {Integer} items.id Item ID.
     * @apiSuccess {Integer} items.sku_id SKU ID.
     * @apiSuccess {String} items.product_name Name of product.
     * @apiSuccess {String} items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} items.tp_item_id Your item reference ID for finance and reporting purpose. Mandatory.
     * @apiSuccess {Integer} items.quantity Number of items purchased.
     * @apiSuccess {Number} items.retail_price Retail price of item.
     * @apiSuccess {Number} items.sale_price Listing price of item, after Hubwire discount has been applied. 0 means no listing price specified.
     * @apiSuccess {Number} items.price Paid price by customer, after Hubwire and Third Party discounts have been applied.
     * @apiSuccess {Number} items.hw_discount Hubwire discount amount applied to this line item.
     * @apiSuccess {Number} items.discount Third Party discount amount applied to this line item.
     * @apiSuccess {Number} items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {String} items.status Status of item. Values: Picking, Picked, Verified, Out of Stock, Cancelled, Returned.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "items": [
     *       {
     *         "id": 16912,
     *         "sku_id": 31,
     *         "product_name": "Giselle Maxi Skirt",
     *         "hubwire_sku": "K-005-WH-M",
     *         "tp_item_id": "2037",
     *         "quantity": 1,
     *         "retail_price": 139,
     *         "sale_price": 0,
     *         "price": 125.10,
     *         "hw_discount": 0,
     *         "discount": 13.90,
     *         "tax": 7.51,
     *         "tax_inclusive": 1,
     *         "status": "Verified"
     *       }
     *     ]
     *   }
     *
     */

    /**
     * @api {get} /sales/:sale_id/items/:item_id Get single item information
     * @apiDescription Get information about an item of an order.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetOrderItem
     * @apiGroup OrdersItems
     *
     * @apiParam {Integer} sale_id The order ID.
     * @apiParam {Integer} item_id The item ID.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} item Item Array.
     * @apiSuccess {Integer} item.id Item ID.
     * @apiSuccess {Integer} item.sku_id SKU ID.
     * @apiSuccess {String} item.product_name Name of product.
     * @apiSuccess {String} item.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} item.tp_item_id Your item reference ID for finance and reporting purpose. Mandatory.
     * @apiSuccess {Integer} item.quantity Number of items purchased.
     * @apiSuccess {Number} item.retail_price Retail price of item.
     * @apiSuccess {Number} item.sale_price Listing price of item, after Hubwire discount has been applied. 0 means no listing price specified.
     * @apiSuccess {Number} item.price Paid price by customer, after Hubwire and Third Party discounts have been applied.
     * @apiSuccess {Number} item.hw_discount Hubwire discount amount applied to this line item.
     * @apiSuccess {Number} item.discount Third Party discount amount applied to this line item.
     * @apiSuccess {Number} item.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} item.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {String} item.status Status of item. Values: Picking, Picked, Verified, Out of Stock, Cancelled, Returned.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "item": {
     *       "id": 16912,
     *       "sku_id": 31,
     *       "product_name": "Giselle Maxi Skirt",
     *       "hubwire_sku": "K-005-WH-M",
     *       "tp_item_id": "2037",
     *       "quantity": 1,
     *       "retail_price": 139,
     *       "sale_price": 0,
     *       "price": 125.10,
     *       "hw_discount": 0,
     *       "discount": 13.90,
     *       "tax": 7.51,
     *       "tax_inclusive": 1,
     *       "status": "Verified"
     *     }
     *   }
     *
     */

    
    /**
     * @api {post} /webhooks Create a webhook
     * @apiDescription Create a new webhook.
     * <p>A webhook is a tool for retrieving and storing data from a certain event.
     * It allows you to register an URL where the event data can be stored in JSON formats.</p>
     * <p>After a specific event happens on Hubwire, for example, after an order is updated to status packing, 
     * Hubwire will check for any webhooks registered to the event and send a HTTP POST request to your registered URL.
     * The request expects status code 200 in the response header. If status code other than 200 is returned, 
     * the request will be considered as fail and the webhook event will be put into queue for retrying. 
     * Webhook events will be removed after <b>5</b> times of failure. By then, an email notification will be sent to you.</p>
     *
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName CreateWebhook
     * @apiGroup Webhooks
     *
     * @apiParam {String} topic The event that will trigger the webhook. Valid values are: sales/created, sales/updated, product/created, product/updated, sku/created, sku/updated.
     * @apiParam {String} address URL where the webhook should send the POST request when the event occurs.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} webhook Webhook Array.
     * @apiSuccess {Integer} webhook.id Webhook ID.
     * @apiSuccess {String} webhook.topic The event that will trigger the webhook.
     * @apiSuccess {String} webhook.address URL where the webhook should send the POST request when the event occurs.
     * @apiSuccess {Date} webhook.created_at Webhook created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} webhook.updated_at Webhook last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "webhook": {
     *       "id": 445,
     *       "topic": "sales/created",
     *       "address": "http://testing.com",
     *       "created_at": "2016-05-24 09:22:04",
     *       "updated_at": "2016-05-24 09:22:04",
     *     }
     *   },
     *
     *
     *
     */
    

     /**
     * @api {get} /webhooks Get a list of all webhooks
     * @apiDescription Get information about a list of all webhooks.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetWebhooks
     * @apiGroup Webhooks
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} webhooks List of webhooks.
     * @apiSuccess {Integer} id Webhook ID.
     * @apiSuccess {String} topic The event that will trigger the webhook.
     * @apiSuccess {String} address URL where the webhook should send the POST request when the event occurs.
     * @apiSuccess {Date} created_at Webhook created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} updated_at Webhook last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "webhooks": [
     *       {
     *         "id": 445,
     *         "topic": "sales/created",
     *         "address": "http://testing.com",
     *         "created_at": "2016-05-24 09:22:04",
     *         "updated_at": "2016-05-24 09:22:04",
     *       }
     *     ]
     *   }
     *
     */

     /**
     * @api {put} /webhooks/:webhook_id Update a webhook
     * @apiDescription Update a webhook.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName UpdateWebhook
     * @apiGroup Webhooks
     *
     * @apiParam {String} topic The event that will trigger the webhook.
     * @apiParam {String} address URL where the webhook should send the POST request when the event occurs.
     *
     * @apiSuccess {Integer} code Response code 200 OK.
     * @apiSuccess {Array} webhook Webhook Array.
     * @apiSuccess {Integer} webhook.id Webhook ID.
     * @apiSuccess {String} webhook.topic The event that will trigger the webhook.
     * @apiSuccess {String} webhook.address URL where the webhook should send the POST request when the event occurs.
     * @apiSuccess {Date} webhook.created_at Webhook created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} webhook.updated_at Webhook last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "webhook": {
     *       "id": 445,
     *       "topic": "sales/created",
     *       "address": "http://testing.com",
     *       "created_at": "2016-05-24 09:22:04",
     *       "updated_at": "2016-05-24 09:22:04"
     *     }
     *   }
     *
     */

     /**
     * @api {delete} /webhooks/:webhook_id Delete a webhook
     * @apiDescription Delete a webhook.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName DeleteWebhook
     * @apiGroup Webhooks
     *
     * @apiParam {Integer} webhook_id Webhook ID.
     *
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "code": 200,
     *     "success": true
     *   }
     *
     */
