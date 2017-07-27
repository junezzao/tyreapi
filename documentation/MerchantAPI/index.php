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
     * @apiParam {String} username
     * @apiParam {String} password 
     * @apiParam {Integer} client_id Your client ID.
     * @apiParam {String} client_secret Your client secret.
     * @apiParam {String} grant_type moobile is currently the only supported value.
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
     *         'username' => 'mobile_user@email.com',
     *         'password' => 'userpassword',
     *         'client_id' => 'da39a3ee5e6b4b0d3255b',
     *         'client_secret' => '30db075010bc5defe2461b3e7a2451427131ed35',
     *         'grant_type' => 'mobile'
     *      ),
     *      CURLOPT_RETURNTRANSFER => 1
     *   ));
     *
     *   // Send the request & save response to $resp
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
     * @api {get} /statistics/merchant/:merchant_id?from_date=:from_date&to_date=:to_date Get merchant statistics
     * @apiDescription Get information about a merchants statistics for a specific date range.
     *
     * @apiVersion 1.0.0
     * @apiUse myHeader
     * @apiName GetStatistics
     * @apiGroup Statistics
     *
     * @apiParam {Integer} merchant_id The merchant ID.
     * @apiParam {String} from_date The from date of the statistics.
     * @apiParam {String} to_date The end date of the statistics.
     *
     * @apiSuccess {Array} channel_performance Total Order Items sold in each Channel sorted by best performing channel on the top.
     * @apiSuccess {String} channel_performance.name Channel Name.
     * @apiSuccess {Integer} channel_performance.count  Total Order Items.
     * @apiSuccess {Array} total_items_sold Total Order Items sold in all channels by day
     * @apiSuccess {String} total_items_sold.date Date of data.
     * @apiSuccess {Integer} total_items_sold.count  Total Order Items on the specified date.
     * @apiSuccess {Array} items_sold_by_channel Total Order Items sold by channels
     * @apiSuccess {Array} items_sold_by_channel.channel_name Total Order Items for the specfied channel
     * @apiSuccess {String} items_sold_by_channel.channel_name.date Date of data.
     * @apiSuccess {Integer} items_sold_by_channel.channel_name.count  Total Order Items on the specified date.
     * @apiSuccess {Array} value_of_items_sold Total Sold Price of  Order Items sold in all channels by day
     * @apiSuccess {String} value_of_items_sold.date Date of data.
     * @apiSuccess {Double} value_of_items_sold.sum  Total Sold Price of Order Items on the specified date.
     * @apiSuccess {Array} value_sold_by_channel Total Sold Price of Order Items sold by channels
     * @apiSuccess {Array} value_sold_by_channel.channel_name Total Sold Price Order Items for the specfied channel
     * @apiSuccess {String} value_sold_by_channel.channel_name.date Date of data.
     * @apiSuccess {Integer} value_sold_by_channel.channel_name.sum  Total Sold Price Order Items on the specified date.
     *
     * @apiSuccessExample {json} Success-Response:
     *    HTTP/1.1 200 OK
     *    {
     *      "channels_performance": [
     *        {
     *          "name": "[Hubwire] Good Virtues Co.",
     *          "count": 88,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Hubwire] Badlab",
     *          "count": 86,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Hubwire] Lazada",
     *          "count": 13,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Hubwire] Zalora",
     *          "count": 6,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Hubwire] 11 street",
     *          "count": 5,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Derigr] Midvalley - Shopify",
     *          "count": 4,
     *          "ref": null
     *        },
     *        {
     *          "name": "[Hubwire] GemFive",
     *          "count": 2,
     *          "ref": null
     *        }
     *      ],
     *      "total_items_sold": [
     *        {
     *          "date": "2016-09-23",
     *          "count": 7,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-24",
     *          "count": 29,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-25",
     *          "count": 18,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-26",
     *          "count": 51,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-27",
     *          "count": 76,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-28",
     *          "count": 19,
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-29",
     *          "count": 4,
     *          "ref": null
     *        }
     *      ],
     *      "items_sold_by_channel": {
     *        "[Hubwire] Zalora": [
     *          {
     *            "date": "2016-09-23",
     *            "count": 1
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "count": 1
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "count": 2
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "count": 2
     *          }
     *        ],
     *        "[Hubwire] Good Virtues Co.": [
     *          {
     *            "date": "2016-09-23",
     *            "count": 4
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "count": 19
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "count": 8
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "count": 18
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "count": 32
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "count": 5
     *          },
     *          {
     *            "date": "2016-09-29",
     *            "count": 2
     *          }
     *        ],
     *        "[Hubwire] Badlab": [
     *          {
     *            "date": "2016-09-23",
     *            "count": 2
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "count": 2
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "count": 8
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "count": 26
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "count": 39
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "count": 9
     *          }
     *        ],
     *        "[Hubwire] Lazada": [
     *          {
     *            "date": "2016-09-24",
     *            "count": 5
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "count": 2
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "count": 3
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "count": 3
     *          }
     *        ],
     *        "[Derigr] Midvalley - Shopify": [
     *          {
     *            "date": "2016-09-24",
     *            "count": 2
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "count": 2
     *          }
     *        ],
     *        "[Hubwire] 11 street": [
     *          {
     *            "date": "2016-09-26",
     *            "count": 5
     *          }
     *        ],
     *        "[Hubwire] GemFive": [
     *          {
     *            "date": "2016-09-29",
     *            "count": 2
     *          }
     *        ]
     *      },
     *      "value_of_items_sold": [
     *        {
     *          "date": "2016-09-23",
     *          "sum": "117.80",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-24",
     *          "sum": "642.20",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-25",
     *          "sum": "337.61",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-26",
     *          "sum": "784.98",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-27",
     *          "sum": "1092.36",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-28",
     *          "sum": "395.33",
     *          "ref": null
     *        },
     *        {
     *          "date": "2016-09-29",
     *          "sum": "106.70",
     *          "ref": null
     *        }
     *      ],
     *      "value_sold_by_channel": {
     *        "[Hubwire] Zalora": [
     *          {
     *            "date": "2016-09-23",
     *            "sum": "42.60"
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "sum": "33.70"
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "sum": "74.00"
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "sum": "77.00"
     *          }
     *        ],
     *        "[Hubwire] Good Virtues Co.": [
     *          {
     *            "date": "2016-09-23",
     *            "sum": "53.20"
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "sum": "366.40"
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "sum": "202.10"
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "sum": "332.90"
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "sum": "471.20"
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "sum": "109.30"
     *          },
     *          {
     *            "date": "2016-09-29",
     *            "sum": "24.60"
     *          }
     *        ],
     *        "[Hubwire] Badlab": [
     *          {
     *            "date": "2016-09-23",
     *            "sum": "22.00"
     *          },
     *          {
     *            "date": "2016-09-24",
     *            "sum": "38.40"
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "sum": "109.71"
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "sum": "294.48"
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "sum": "469.26"
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "sum": "108.68"
     *          }
     *        ],
     *        "[Hubwire] Lazada": [
     *          {
     *            "date": "2016-09-24",
     *            "sum": "177.90"
     *          },
     *          {
     *            "date": "2016-09-26",
     *            "sum": "102.60"
     *          },
     *          {
     *            "date": "2016-09-27",
     *            "sum": "77.90"
     *          },
     *          {
     *            "date": "2016-09-28",
     *            "sum": "100.35"
     *          }
     *        ],
     *        "[Derigr] Midvalley - Shopify": [
     *          {
     *            "date": "2016-09-24",
     *            "sum": "25.80"
     *          },
     *          {
     *            "date": "2016-09-25",
     *            "sum": "25.80"
     *          }
     *        ],
     *        "[Hubwire] 11 street": [
     *          {
     *            "date": "2016-09-26",
     *            "sum": "55.00"
     *          }
     *        ],
     *        "[Hubwire] GemFive": [
     *          {
     *            "date": "2016-09-29",
     *            "sum": "82.10"
     *          }
     *        ]
     *      }
     *    } 
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
     * @apiParam {Integer} /sale_id The order ID.
     *
     * @apiSuccess {Integer} id Order ID.
     * @apiSuccess {String} order_number Your order number for reference.
     * @apiSuccess {Date} order_date Your order date for reference in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Number} total_price Sum of all prices of all items in the order, taxes, shipping_fee and discounts included.
     * @apiSuccess {Number} total_discount Total amount of discounts.
     * @apiSuccess {Number} shipping_fee Total shipping amount applied.
     * @apiSuccess {String} currency Three-letter currency code used for payment.
     * @apiSuccess {String} payment_type Type of payment processing method.
     * @apiSuccess {String} status Status of the order.
     * @apiSuccess {Date} created_at Order created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} updated_at Order last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Array} shipping_info Shipping information of the order.
     * @apiSuccess {String} shipping_info.recipient Name of recipient.
     * @apiSuccess {String} shipping_info.phone Phone number of recipient.
     * @apiSuccess {String} shipping_info.tracking_no Tacking number provided by shipping courier.
     * @apiSuccess {String} shipping_info.address_1 Street address.
     * @apiSuccess {String} shipping_info.address_2 Optional additional field for street address.
     * @apiSuccess {String} shipping_info.city City of shipping address.
     * @apiSuccess {String} shipping_info.postcode Postal code. of shipping address
     * @apiSuccess {String} shipping_info.state State of shipping address.
     * @apiSuccess {String} shipping_info.country Country of shipping address.
     * @apiSuccess {Array} items List of items of the order.
     * @apiSuccess {Integer} items.id Item ID.
     * @apiSuccess {Integer} items.sku_id SKU ID.
     * @apiSuccess {String} items.product_name Name of product.
     * @apiSuccess {String} items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} items.quantity Number of items purchased.
     * @apiSuccess {Integer} items.returned_quantity Number of items returned.
     * @apiSuccess {Integer} items.price Price of item before discounts have been applied.
     * @apiSuccess {Number} items.discount Discount amount applied to this line item.
     * @apiSuccess {Number} items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {Array} customer Customer information of the order.
     * @apiSuccess {Integer} customer.id Customer ID.
     * @apiSuccess {String} customer.name Customer's name.
     * @apiSuccess {String} customer.email Customer's email address.
     * @apiSuccess {String} customer.phone Customer's phone number.
     * @apiSuccess {Date} customer.created_at Customer created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} customer.updated_at Customer last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "id": 1020,
     *     "order_number": "#6730",
     *     "order_date": "2016-05-11 02:20:00",
     *     "total_price": 145,
     *     "total_discount": 0,
     *     "shipping_fee": 6,
     *     "currency": "MYR",
     *     "payment_type": "paypal",
     *     "status": "paid",
     *     "created_at": "2016-05-11 02:22:50",
     *     "updated_at": "2016-05-11 02:22:50",
     *     "shipping_info": {    
     *       "recipient": "Amy Shoon",
     *       "phone": "0139855381",
     *       "tracking_no": "EH144050790MY",
     *       "address_1": "Unit 17-7 Jalan PJU 1/41",
     *       "address_2": "Dataran Prima",
     *       "city": "Petaling Jaya",
     *       "postcode": 47301,
     *       "state": "Selangor",
     *       "country": "Malaysia"
     *     },
     *     "items": [
     *       {
     *         "id": 16912,
     *         "sku_id": 31,
     *         "product_name": "Giselle Maxi Skirt",
     *         "hubwire_sku": "K-005-WH-M",
     *         "quantity": 1,
     *         "returned_quantity": 0,
     *         "price": 139,
     *         "discount": 0,
     *         "tax": 7.86,
     *         "tax_inclusive": 1
     *     ],
     *     "customer": {
     *       "id": 1432,
     *       "name": "Joseph Adam",
     *       "email": "joseph.adam@gmail.com",
     *       "phone": "0197333126",
     *       "created_at": "2016-05-11 02:22:50",
     *       "updated_at": "2016-05-11 02:22:50",
     *     } 
     *   }
     *
     */

    /**
     * @api {post} /sales Get a list of orders
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
     * @apiSuccess {Integer} start Results restricted to after this specified index.
     * @apiSuccess {Integer} limit Current amount of results.
     * @apiSuccess {Integer} total Total amount of results.
     * @apiSuccess {Array} sales List of orders.
     * @apiSuccess {Integer} sales.id Order ID.
     * @apiSuccess {String} sales.order_number Your order number for reference.
     * @apiSuccess {Date} sales.order_date Your order date for reference in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Number} sales.total_price Sum of all prices of all items in the order, taxes, shipping_fee and discounts included.
     * @apiSuccess {Number} sales.total_discount Total amount of discounts.
     * @apiSuccess {Number} sales.shipping_fee Total shipping amount applied.
     * @apiSuccess {String} sales.currency Three-letter currency code used for payment.
     * @apiSuccess {String} sales.payment_type Type of payment processing method.
     * @apiSuccess {String} sales.status Status of the order.
     * @apiSuccess {Date} sales.created_at Order created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} sales.updated_at Order last updated date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Array} sales.shipping_info Shipping information of the order.
     * @apiSuccess {String} sales.shipping_info.recipient Name of recipient.
     * @apiSuccess {String} sales.shipping_info.phone Phone number of recipient.
     * @apiSuccess {String} sales.shipping_info.tracking_no Tacking number provided by shipping courier.
     * @apiSuccess {String} sales.shipping_info.address_1 Street address.
     * @apiSuccess {String} sales.shipping_info.address_2 Optional additional field for street address.
     * @apiSuccess {String} sales.shipping_info.city City of shipping address.
     * @apiSuccess {String} sales.shipping_info.postcode Postal code. of shipping address
     * @apiSuccess {String} sales.shipping_info.state State of shipping address.
     * @apiSuccess {String} sales.shipping_info.country Country of shipping address.
     * @apiSuccess {Array} sales.items List of items of the order.
     * @apiSuccess {Integer} sales.items.id Item ID.
     * @apiSuccess {Integer} sales.items.sku_id SKU ID.
     * @apiSuccess {String} sales.items.product_name Name of product.
     * @apiSuccess {String} sales.items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} sales.items.quantity Number of items purchased.
     * @apiSuccess {Integer} sales.items.returned_quantity Number of items returned.
     * @apiSuccess {Integer} sales.items.price Price of item before discounts have been applied.
     * @apiSuccess {Number} sales.items.discount Discount amount applied to this line item.
     * @apiSuccess {Number} sales.items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} sales.items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     * @apiSuccess {Array} sales.customer Customer information of the order.
     * @apiSuccess {Integer} sales.customer.id Customer ID.
     * @apiSuccess {String} sales.customer.name Customer's name.
     * @apiSuccess {String} sales.customer.email Customer's email address.
     * @apiSuccess {String} sales.customer.phone Customer's phone number.
     * @apiSuccess {Date} sales.customer.created_at Customer created date in YYYY-MM-DD H:i:s format.
     * @apiSuccess {Date} sales.customer.updated_at Customer last updated date in YYYY-MM-DD H:i:s format.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "start": 0,
     *     "limit": 50,
     *     "total": 79,
     *     "sales": [
     *       {
     *         "id": 1020,
     *         "order_number": "#6730",
     *         "order_date": "2016-05-11 02:20:00",
     *         "total_price": 145,
     *         "total_discount": 0,
     *         "shipping_fee": 6,
     *         "currency": "MYR",
     *         "payment_type": "paypal",
     *         "status": "paid",
     *         "created_at": "2016-05-11 02:22:50",
     *         "updated_at": "2016-05-11 02:22:50",
     *         "shipping_info": {    
     *           "recipient": "Amy Shoon",
     *           "phone": "0139855381",
     *           "tracking_no": "EH144050790MY",
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
     *             "quantity": 1,
     *             "returned_quantity": 0,
     *             "price": 139,
     *             "discount": 0,
     *             "tax": 7.86,
     *             "tax_inclusive": 1
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
     * @apiSuccess {Array} items List of items of the order.
     * @apiSuccess {Integer} items.id Item ID.
     * @apiSuccess {Integer} items.sku_id SKU ID.
     * @apiSuccess {String} items.product_name Name of product.
     * @apiSuccess {String} items.hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} items.quantity Number of items purchased.
     * @apiSuccess {Integer} items.returned_quantity Number of items returned.
     * @apiSuccess {Integer} items.price Price of item before discounts have been applied.
     * @apiSuccess {Number} items.discount Discount amount applied to this line item.
     * @apiSuccess {Number} items.tax Amount of tax to be charged.
     * @apiSuccess {Boolean} items.tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "items": [
     *       {
     *         "id": 16912,
     *         "sku_id": 31,
     *         "product_name": "Giselle Maxi Skirt",
     *         "hubwire_sku": "K-005-WH-M",
     *         "quantity": 1,
     *         "returned_quantity": 0,
     *         "price": 139,
     *         "discount": 0,
     *         "tax": 7.86,
     *         "tax_inclusive": 1
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
     * @apiSuccess {Integer} id Item ID.
     * @apiSuccess {Integer} sku_id SKU ID.
     * @apiSuccess {String} product_name Name of product.
     * @apiSuccess {String} hubwire_sku Unique Hubwire SKU ID.
     * @apiSuccess {Integer} quantity Number of items purchased.
     * @apiSuccess {Integer} returned_quantity Number of items returned.
     * @apiSuccess {Integer} price Price of item before discounts have been applied.
     * @apiSuccess {Number} discount Discount amount applied to this line item.
     * @apiSuccess {Number} tax Amount of tax to be charged.
     * @apiSuccess {Boolean} tax_inclusive Indicate whether taxes are included in this line item price. 0 = exclusive, 1 = inclusive.
     *
     * @apiSuccessExample {json} Success-Response:
     *   HTTP/1.1 200 OK
     *   {
     *     "id": 16912,
     *     "sku_id": 31,
     *     "product_name": "Giselle Maxi Skirt",
     *     "hubwire_sku": "K-005-WH-M",
     *     "quantity": 1,
     *     "returned_quantity": 0,
     *     "price": 139,
     *     "discount": 0,
     *     "tax": 7.86,
     *     "tax_inclusive": 1
     *   }
     *
     */
