<?php

namespace App\Http\Controllers\Partner;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Requests\Partner\StockTransferRequest as StockTransferRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\StockTransferRepositoryContract;
use App\Repositories\Contracts\ChannelRepositoryContract;
use App\Repositories\Contracts\PartnerRepositoryContract;
use App\Repositories\Contracts\OAuthRepositoryContract;
use Cache;

class DistributionCenterController extends Controller
{
    
    const DISTRIB_CENTER = 1;
    private $partner_id;



 
    public function __construct(Request $request)
    {
        $this->partner_id = $request->header('partnerid');
        // \Log::info($this->partner_id);
    }

    /*public function index(PartnerRepositoryContract $partnerRepo)
    {
        $distribution_centers = $partnerRepo->getDistributionCenters($this->partner_id);
        return response()->json(array(
            'distribution_centers' => $distribution_centers,
        ));
    }*/

    /**
     *@apiDefine myHeader
     * @apiHeader {String} accesstoken Access Token value.
     * @apiHeader {Integer} partnerid Partner Unique Id.
     * @apiHeader {Datetime} timestamp Timestamp.
     */

    /**
     * @api {get} /distribution_centers Distribution Center index
     * @apiDescription The API description 
     * Another description
     * Another description
     *
     * @apiUse myHeader
     * @apiVersion 1.0.0
     * @apiName GetDistributionCenters
     * @apiGroup DistributionCenters
     *
     * @apiSuccess {Array} distribution_centers Distribution Center array list.
     * @apiSuccess {Integer} distribution_centers.distribution_center_id Distribution Center unique id.
     * @apiSuccess {String} distribution_centers.name  Name of the Distribution Center.
     * @apiSuccess {String} distribution_centers.address  Distribution Center address.
     * @apiSuccess {Array} distribution_centers.client  Client Information array.
     * @apiSuccess {String} distribution_centers.client.client_name Client Name of the Distribution Center.
     * @apiSuccess {String} distribution_centers.client.client_contact_person Client Contact Person of the Distribution Center.
     * @apiSuccess {String} distribution_centers.client.client_address  Client address.
     * @apiSuccess {String} distribution_centers.client.client_timezone  Client timezone.
     * @apiSuccess {String} distribution_centers.client.client_currency  Client currency.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *     "distribution_centers": [
     *       {
     *        "distribution_center_id": 1821,
     *         "name": "Test DistributionCenter",
     *         "address": "73101 VonRueden Views\nGrahamchester, AZ 37400-4814",
     *         "client": {
     *           "client_name": "Rogahn-Beier",
     *           "client_contact_person": "Jonathan Walsh I",
     *           "client_address": "794 Jackie Hollow\nWinstonland, NE 50982-6967",
     *           "client_timezone": "Asia/Kuala_Lumpur",
     *           "client_currency": "MYR"
     *         }
     *       },
     *       {
     *         "distribution_center_id": 1822,
     *         "name": "Test DistributionCenter 2",
     *         "address": "04207 Dominique Shore Apt. 696\nSwiftside, OR 60889-1772",
     *         "client": {
     *           "client_name": "Rogahn-Beier",
     *           "client_contact_person": "Jonathan Walsh I",
     *           "client_address": "794 Jackie Hollow\nWinstonland, NE 50982-6967",
     *           "client_timezone": "Asia/Kuala_Lumpur",
     *           "client_currency": "MYR"
     *         }
     *       }
     *     ]
     *   }
     *
     */
    public function index(PartnerRepositoryContract $partnerRepo)
    {
        $distribution_centers = Cache::remember('distribution_centers'.$this->partner_id, env('CACHE_TIME'), function () use ($partnerRepo) {
            return $partnerRepo->getDistributionCenters($this->partner_id);
        });
        return response()->json(array(
            'distribution_centers' => $distribution_centers
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * @api {get} /distribution_centers/:dc_id  Distribution Center information
     * @apiDescription The API description 
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiVersion 1.0.0
     * @apiName GetDistributionCenter
     * @apiGroup DistributionCenters
     *
     * @apiSuccess {Integer} distribution_center_id Distribution Center unique id.
     * @apiSuccess {String} name  Name of the Distribution Center.
     * @apiSuccess {String} address  Distribution Center address.
     * @apiSuccess {Array} client  Client Information array.
     * @apiSuccess {String} client.client_name Client Name of the Distribution Center.
     * @apiSuccess {String} client.client_contact_person Client Contact Person of the Distribution Center.
     * @apiSuccess {String} client.client_address  Client address.
     * @apiSuccess {String} client.client_timezone  Client timezone.
     * @apiSuccess {String} client.client_currency  Client currency.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *        "distribution_center_id": 1821,
     *         "name": "Test DistributionCenter",
     *         "address": "73101 VonRueden Views\nGrahamchester, AZ 37400-4814",
     *         "client": {
     *           "client_name": "Rogahn-Beier",
     *           "client_contact_person": "Jonathan Walsh I",
     *           "client_address": "794 Jackie Hollow\nWinstonland, NE 50982-6967",
     *           "client_timezone": "Asia/Kuala_Lumpur",
     *           "client_currency": "MYR"
     *      }
     *
     * @apiErrorExample {json} Invalid-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "distribution_center_id": [
     *             "The selected distribution center id is invalid."
     *           ]
     *         }
     *       }
     */
    public function show(PartnerRepositoryContract $partnerRepo, $dc_id)
    {
        $response = Cache::remember('distribution_center'.$dc_id, env('CACHE_TIME'), function () use ($partnerRepo, $dc_id) {
            return $partnerRepo->getDistributionCenter($dc_id, $this->partner_id);
        });
        return response()->json($response);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }
    
    /**
     * @api {post} /distribution_centers/transfers Create a Stock Transfer between Distribution Centers
     * @apiDescription The API description 
     * @apiUse myHeader
     * @apiVersion 1.0.0
     * @apiName PostStockTransfers
     * @apiGroup StockTransfers
     * @apiParam {Integer} source_id <code>Required</code> Distribution Center Unique Id (Source).
     * @apiParam {Integer} recipient_id  <code>Required</code> Distribution Center Unique Id (Target).
     * @apiParam {Array} sku_list <code>Required</code> List of the SKUs.
     * @apiParam {Integer} sku_list.sku_id <code>Required</code> SKU/Product Unique Id.
     * @apiParam {Integer} sku_list.quantity <code>Required</code> Quantity to transfer, min=1.
     * @apiParamExample {json} Request-Example:
     *     {
     *       "source_id": 4711,
     *       "recipient_id": 5500,
     *       "sku_list": [
     *              {
     *                  "sku_id": 1720,
     *                  "quantity": 2 
     *              },
     *              {
     *                  "sku_id": 1880,
     *                  "quantity": 5 
     *              },
     *              {
     *                  "sku_id": 2220,
     *                  "quantity": 8 
     *              }
     *           ]    
     *     }
     *
     *
     * @apiSuccess {Array} transfer Transfer array.
     * @apiSuccess {Integer} transfer.transfer_id Distribution Center unique id.
     * @apiSuccess {String} transfer.sent_at  Name of the Distribution Center.
     * @apiSuccess {String} transfer.receive_at Client Name of the Distribution Center.
     * @apiSuccess {String} transfer.status Status of the transfer.
     * @apiSuccess {Integer} transfer.source_id  Distribution Center Unique Id (Source).
     * @apiSuccess {Integer} transfer.recipient_id  Distribution Center Unique Id (Target).
     * @apiSuccess {Array} transfer.sku_list  SKU Array.
     * @apiSuccess {Integer} transfer.sku_list.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} transfer.sku_list.product_name  The product name.
     * @apiSuccess {String} transfer.sku_list.hubwire_sku  Hubwire SKU Unique Id.
     * @apiSuccess {Integer} transfer.sku_list.quantity  Current available stock.
     * @apiSuccess {Integer} transfer.sku_list.transfer_quantity  Quantity being transfered.
     * @apiSuccess {String} transfer.sku_list.barcode  Product barcode.
     * @apiSuccess {Number} transfer.sku_list.weight  Product weight in grams.
     * @apiSuccess {Text} transfer.remarks  Stock Transfer remarks.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "transfer": {
     *           "transfer_id": 79,
     *           "sent_at": "2016-02-15 08:39:05",
     *           "receive_at": null,
     *           "status": "1",
     *           "source_id": 1821,
     *           "recipient_id": 1822,
     *           "sku_list": [
     *             {
     *               "sku_id": "1817",
     *               "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *               "hubwire_sku": "2196102365838",
     *               "quantity": "602",
     *               "transfer_quantity": "2",
     *               "barcode": "6692589714566",
     *               "weight": "402.00"
     *             }
     *           ],
     *           "remarks": "Partner Stock Transfer"
     *         }
     *       }
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "source_id": [
     *             "The source id field is required."
     *           ],
     *           "recipient_id": [
     *             "The recipient id field is required."
     *           ],
     *           "sku_list": [
     *             "The sku list field is required."
     *           ]
     *         }
     *       }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "source_id": [
     *             "The source id field is required."
     *           ],
     *           "recipient_id": [
     *             "The recipient id field is required."
     *           ],
     *           "sku_list": [
     *             "The sku list field is required."
     *           ]
     *         }
     *       }
     */
    public function stockTransfer(
        StockTransferRequest $request,
        PartnerRepositoryContract $partnerRepo,
        StockTransferRepositoryContract $stockTransferRepo)
    {
        $inputs =  $request->all();
        $dc1 = $partnerRepo->getDistributionCenterDetails($inputs['source_id'], $this->partner_id, ['distribution_ch_id', 'channels.client_id']);
        $dc2 = $partnerRepo->getDistributionCenterDetails($inputs['recipient_id'], $this->partner_id, ['distribution_ch_id', 'channels.client_id']);
        $response = $stockTransferRepo->createStockTransfer(
                                            $dc1->distribution_ch_id,
                                            $dc2->distribution_ch_id,
                                            $inputs['sku_list'],
                                            $dc1->client_id
                                        );
        return response()->json($response);
    }
    /**
     * @api {get} /distribution_centers/:dc_id/products Distribution Center products index
     * @apiDescription The API description 
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiVersion 1.0.0
     * @apiName GetDistributionCenterProducts
     * @apiGroup DistributionCenters
     *
     * @apiSuccess {Array} products  Products array.
     * @apiSuccess {String} products.sku_id SKU/Product Unique Id.
     * @apiSuccess {String} products.product_name The product name.
     * @apiSuccess {String} products.hubwire_sku  Hubwire SKU Unique Id.
     * @apiSuccess {String} products.quantity  Current available stock.
     * @apiSuccess {String} products.barcode  Product barcode.
     * @apiSuccess {Number} products.weight  Product weight in Grams.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "products": [
     *           {
     *             "sku_id": "1818",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "9446857039069",
     *             "quantity": "326",
     *             "barcode": "3325125300628",
     *             "weight": "427.00"
     *           }
     *         ]
     *       }   
     *
     * @apiErrorExample {json} Invalid-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "distribution_center_id": [
     *             "The selected distribution center id is invalid."
     *           ]
     *         }
     *       }
     */
    public function products(
        ChannelRepositoryContract $channelRepo,
        PartnerRepositoryContract $partnerRepo,
        $dc_id)
    {
        $dc = $partnerRepo->getDistributionCenterDetails($dc_id, $this->partner_id, ['distribution_ch_id']);
        $response = Cache::remember('products'.$dc_id, env('CACHE_TIME'), function () use ($channelRepo, $dc) {
            return $channelRepo->getChannelProducts($dc->distribution_ch_id);
        });
        return response()->json(array(
                'products' => $response->products
        ));
    }
    /**
     * @api {get} /distribution_centers/:dc_id/products/:sku_id Distribution Center products information
     * @apiDescription The API description 
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiParam {Integer} skud SKU/Product Unique Id.
     * @apiVersion 1.0.0
     * @apiName GetDistributionCenterProductDetails
     * @apiGroup DistributionCenters
     *
     * @apiSuccess {String} sku_id SKU/Product Unique Id.
     * @apiSuccess {String} product_name The product name.
     * @apiSuccess {String} hubwire_sku  Hubwire SKU Id.
     * @apiSuccess {String} quantity  Product Quantity in Stock.
     * @apiSuccess {String} barcode  Product Barcode.
     * @apiSuccess {Number} weight  Product weight in Grams.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *        "sku_id": "1818",
     *        "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *        "hubwire_sku": "9446857039069",
     *        "quantity": "326",
     *        "barcode": "3325125300628",
     *        "weight": "427.00"
     *     }
     *
     * @apiErrorExample {json} Invalid-Response:
     *     HTTP/1.1 404 Not Found
     *     {
     *         "code": 404,
     *         "error": "Page or data not found. Make sure the URL is correct."
     *      }
     */
    public function product(
        PartnerRepositoryContract $partnerRepo,
        ChannelRepositoryContract $channelRepo,
        $dc_id, $sku_id)
    {
        $dc = $partnerRepo->getDistributionCenterDetails($dc_id, $this->partner_id, ['distribution_ch_id']);
        $response = Cache::remember('product'.$sku_id, env('CACHE_TIME'), function () use ($dc, $sku_id, $channelRepo) {
            return $channelRepo->getChannelProduct($dc->distribution_ch_id, $sku_id);
        });
        return response()->json(
                $response->products[0]
        );
    }
    /**
     * @api {post} /distribution_centers/:dc_id/replenish Create a Stock Replenishment for a Distribution Center
     * @apiDescription The API description 
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiVersion 1.0.0
     * @apiName PostReplenishment
     * @apiGroup StockTransfers
     * @apiParam {Array} sku_list <code>Required</code> List of the SKUs.
     * @apiParam {Integer} sku_list.sku_id <code>Required</code> SKU/Product Unique Id.
     * @apiParam {Integer} sku_list.quantity <code>Required</code> Quantity to replenish, min=1.
     * @apiParamExample {json} Request-Example:
     *     {
     *       "sku_list": [
     *              {
     *                  "sku_id": 1720,
     *                  "quantity": 2 
     *              },
     *              {
     *                  "sku_id": 1880,
     *                  "quantity": 5 
     *              },
     *              {
     *                  "sku_id": 2220,
     *                  "quantity": 8 
     *              }
     *           ]    
     *     }
     *
     *
     * @apiSuccess {Array} replenishment Replenishment array.
     * @apiSuccess {Integer} replenishment.replenish_id Replenishment Unique Id.
     * @apiSuccess {DateTime} replenishment.replenishment_date  Date of the replenishment.
     * @apiSuccess {Integer} replenishment.client_id Client unique id.
     * @apiSuccess {Integer} replenishment.replenish_status Status of the replenishment.
     * @apiSuccess {Integer} replenishment.distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Array} replenishment.sku_list  SKU/Product array.
     * @apiSuccess {Integer} replenishment.sku_list.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} replenishment.sku_list.product_name  SKU/Product name.
     * @apiSuccess {String} replenishment.sku_list.barcode  SKU/Product barcode.
     * @apiSuccess {String} replenishment.sku_list.hubwire_sku  Hubwire SKU Id.
     * @apiSuccess {String} replenishment.sku_list.replenish_quantity  Quantity being replenish.
     * @apiSuccess {String} replenishment.sku_list.replenish_status SKU/Product replenish status.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "replenishment": {
     *           "replenish_id": 80,
     *           "replenish_date": "2016-02-16",
     *           "client_id": "911",
     *           "replenish_status": "0",
     *           "distribution_center_id": "1821",
     *           "sku_list": [
     *             {
     *               "sku_id": "1817",
     *               "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *               "barcode": "6692589714566",
     *               "hubwire_sku": "2196102365838",
     *               "replenish_quantity": "2",
     *               "replenish_status": "0"
     *             }
     *           ]
     *         }
     *       }
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "sku_list": [
     *             "The sku list field is required."
     *           ]
     *         }
     *       }
     */
    public function replenish($dc_id,
        Request $request,
        PartnerRepositoryContract $partnerRepo,
        ChannelRepositoryContract $channelRepo
        ) {
        $dc = $partnerRepo->getDistributionCenterDetails($dc_id, $this->partner_id, ['distribution_ch_id']);
        $batch = $channelRepo->replenishment($dc->distribution_ch_id, $request->input('sku_list'), $dc_id);
        return response()->json([
            'replenishment' => $batch
        ]);
    }

    /**
     * @api {get} /distribution_centers/orders Order index
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiVersion 1.0.0
     * @apiName GetPartnerOrders
     * @apiGroup Orders
     *
     * @apiSuccess {Array} orders Orders array.
     * @apiSuccess {Integer} orders.order_id Order Unique Id.
     * @apiSuccess {String} orders.order_status  Status of the order.
     * @apiSuccess {String} orders.customer_name  Customer full name.
     * @apiSuccess {Address} orders.customer_address  Customer shipping address.
     * @apiSuccess {Phone} orders.customer_phone  Customer contact phone number.
     * @apiSuccess {Integer} orders.fulfillment_status  Fulfillment status of the order.
     * @apiSuccess {Number} orders.tracking_number  Shipping tracking number.
     * @apiSuccess {Integer} orders.distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Array} orders.order_items  Order Items array.
     * @apiSuccess {Integer} orders.order_items.item_id Unique Id for an item for the order.
     * @apiSuccess {Integer} orders.order_items.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} orders.order_items.product_name  SKU/Product name.
     * @apiSuccess {Integer} orders.order_items.hubwire_sku  Hubwire SKU/Product Unique Id.
     * @apiSuccess {String} orders.order_items.barcode  SKU/Product barcode.
     * @apiSuccess {Integer} orders.order_items.order_quantity  Order Item quantity.
     * @apiSuccess {Integer} orders.order_items.original_quantity  Order Item original quantity.
     * @apiSuccess {Array} orders.order_items.options  SKU/Product options or variantion type.
     * @apiSuccess {Integer} orders.order_items.options.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} orders.order_items.options.option_name  Option name e.g. Colour, Size, Material etc.
     * @apiSuccess {String} orders.order_items.options.option_value  The option value e.g. White, M, Cotton etc.
     * @apiSuccess {Datetime} orders.created_at  Order datetime created.
     * @apiSuccess {Datetime} orders.updated_at Order datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "orders": [
     *           {
     *             "order_id": 1764,
     *             "order_status": "paid",
     *             "customer_name": "Timothy Miller",
     *             "customer_address": "697 Leuschke Row\nJalonland, MN 59100-3392",
     *             "customer_phone": "(313)004-4248",
     *             "fulfillment_status": "0",
     *             "tracking_number": null,
     *             "distribution_center_id": 1821,
     *             "order_items": [
     *               {
     *                 "item_id": 3429,
     *                 "sku_id": "1817",
     *                 "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *                 "hubwire_sku": "2196102365838",
     *                 "barcode": "6692589714566",
     *                 "order_quantity": "4",
     *                 "original_quantity": "4",
     *                 "options": [
     *                   {
     *                     "sku_id": "1817",
     *                     "option_name": "Colour",
     *                     "option_value": "fuchsia"
     *                   }
     *                 ]
     *               },
     *               {
     *                 "item_id": 3430,
     *                 "sku_id": "1818",
     *                 "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *                 "hubwire_sku": "9446857039069",
     *                 "barcode": "3325125300628",
     *                 "order_quantity": "9",
     *                 "original_quantity": "9",
     *                 "options": [
     *                   {
     *                     "sku_id": "1818",
     *                     "option_name": "Colour",
     *                     "option_value": "navy"
     *                   }
     *                 ]
     *               }
     *             ],
     *             "created_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "updated_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             }
     *           },
     *           {
     *             "order_id": 1765,
     *             "order_status": "paid",
     *             "customer_name": "Francisca Spinka DVM",
     *             "customer_address": "17818 Eliseo Springs Apt. 179\nWest Kaciestad, RI 22205",
     *             "customer_phone": "461-345-2288x216",
     *             "fulfillment_status": "0",
     *             "tracking_number": null,
     *             "distribution_center_id": 1822,
     *             "order_items": [
     *               {
     *                 "item_id": 3431,
     *                 "sku_id": "1817",
     *                 "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *                 "hubwire_sku": "2196102365838",
     *                 "barcode": "6692589714566",
     *                 "order_quantity": "5",
     *                 "original_quantity": "5",
     *                 "options": [
     *                   {
     *                     "sku_id": "1817",
     *                     "option_name": "Colour",
     *                     "option_value": "fuchsia"
     *                   }
     *                 ]
     *               },
     *               {
     *                 "item_id": 3432,
     *                 "sku_id": "1818",
     *                 "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *                 "hubwire_sku": "9446857039069",
     *                 "barcode": "3325125300628",
     *                 "order_quantity": "1",
     *                 "original_quantity": "1",
     *                 "options": [
     *                   {
     *                     "sku_id": "1818",
     *                     "option_name": "Colour",
     *                     "option_value": "navy"
     *                   }
     *                 ]
     *               }
     *             ],
     *             "created_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "updated_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             }
     *           }
     *         ]
     *       }
     *  
     *
     */

    public function orders(PartnerRepositoryContract $partnerRepo)
    {
        $orders = Cache::remember('orders'.$this->partner_id, env('CACHE_TIME'), function () use ($partnerRepo) {
            return $partnerRepo->getPartnerOrders($this->partner_id);
        });
        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * @api {get} /distribution_centers/orders/:order_id Order information
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} order_id Order Unique Id.
     * @apiVersion 1.0.0
     * @apiName GetOrderInfo
     * @apiGroup Orders
     *
     * @apiSuccess {Integer} order_id Order Unique Id.
     * @apiSuccess {String} order_status  Status of the order.
     * @apiSuccess {String} customer_name  Customer full name.
     * @apiSuccess {Address} customer_address  Customer shipping address.
     * @apiSuccess {Phone} customer_phone  Customer contact phone number.
     * @apiSuccess {Integer} fulfillment_status  Fulfillment status of the order.
     * @apiSuccess {Number} tracking_number  Shipping tracking number.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Array} order_items  Order Items array.
     * @apiSuccess {Integer} order_items.item_id Unique Id for an item for the order.
     * @apiSuccess {Integer} order_items.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.product_name  SKU/Product name.
     * @apiSuccess {Integer} order_items.hubwire_sku  Hubwire SKU/Product Unique Id.
     * @apiSuccess {String} order_items.barcode  SKU/Product barcode.
     * @apiSuccess {Integer} order_items.order_quantity  Order Item quantity.
     * @apiSuccess {Integer} order_items.original_quantity  Order Item original quantity.
     * @apiSuccess {Array} order_items.options  SKU/Product options or variantion type.
     * @apiSuccess {Integer} order_items.options.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.options.option_name  Option name e.g. Colour, Size, Material etc.
     * @apiSuccess {String} order_items.options.option_value  The option value e.g. White, M, Cotton etc.
     * @apiSuccess {Datetime} created_at  Order datetime created.
     * @apiSuccess {Datetime} updated_at Order datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "order_id": 1764,
     *         "order_status": "paid",
     *         "customer_name": "Timothy Miller",
     *         "customer_address": "697 Leuschke Row\nJalonland, MN 59100-3392",
     *         "customer_phone": "(313)004-4248",
     *         "fulfillment_status": "0",
     *         "tracking_number": null,
     *         "distribution_center_id": 1821,
     *         "order_items": [
     *           {
     *             "item_id": 3429,
     *             "sku_id": "1817",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "2196102365838",
     *             "barcode": "6692589714566",
     *             "order_quantity": "4",
     *             "original_quantity": "4",
     *             "options": [
     *               {
     *                 "sku_id": "1817",
     *                 "option_name": "Colour",
     *                 "option_value": "fuchsia"
     *               }
     *             ]
     *           },
     *           {
     *             "item_id": 3430,
     *             "sku_id": "1818",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "9446857039069",
     *             "barcode": "3325125300628",
     *             "order_quantity": "9",
     *             "original_quantity": "9",
     *             "options": [
     *               {
     *                 "sku_id": "1818",
     *                 "option_name": "Colour",
     *                 "option_value": "navy"
     *               }
     *             ]
     *           }
     *         ],
     *         "created_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "updated_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         }
     *    }
     *  
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 404,
     *         "error": "Page or data not found. Make sure the URL is correct."
     *     }
     */

    public function order(PartnerRepositoryContract $partnerRepo, $order_id)
    {
        $order = Cache::remember('order'.$this->partner_id, env('CACHE_TIME'), function () use ($partnerRepo, $order_id) {
            return $partnerRepo->getOrder($order_id, $this->partner_id);
        });
        return response()->json($order);
    }

    public function dc_orders(PartnerRepositoryContract $partnerRepo, $dc_id)
    {
    }
    /**
     * @api {post} /distribution_centers/orders/:order_id/update Update order 
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} order_id Order Unique Id.
     * @apiVersion 1.0.0
     * @apiName UpdateOrder
     * @apiGroup Orders
     * @apiParam {String} order_status <code>Required</code> Packing, Shipped, Completed, Pending, Failed.
     * @apiParam {String} tracking_number <code>Optional</code> Tracking Number; required when "order_status" Shipped.
     * @apiParamExample {json} Request-Example:
     *     {
     *       "order_status": "shipped",
     *       "tracking_number": "783646371100"     
     *     }
     *
     * @apiSuccess {Integer} order_id Order Unique Id.
     * @apiSuccess {String} order_status  Status of the order.
     * @apiSuccess {String} customer_name  Customer full name.
     * @apiSuccess {Address} customer_address  Customer shipping address.
     * @apiSuccess {Phone} customer_phone  Customer contact phone number.
     * @apiSuccess {Integer} fulfillment_status  Fulfillment status of the order.
     * @apiSuccess {Number} tracking_number  Shipping tracking number.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Array} order_items  Order Items array.
     * @apiSuccess {Integer} order_items.item_id Unique Id for an item for the order.
     * @apiSuccess {Integer} order_items.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.product_name  SKU/Product name.
     * @apiSuccess {Integer} order_items.hubwire_sku  Hubwire SKU/Product Unique Id.
     * @apiSuccess {String} order_items.barcode  SKU/Product barcode.
     * @apiSuccess {Integer} order_items.order_quantity  Order Item quantity.
     * @apiSuccess {Integer} order_items.original_quantity  Order Item original quantity.
     * @apiSuccess {Array} order_items.options  SKU/Product options or variantion type.
     * @apiSuccess {Integer} order_items.options.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.options.option_name  Option name e.g. Colour, Size, Material etc.
     * @apiSuccess {String} order_items.options.option_value  The option value e.g. White, M, Cotton etc.
     * @apiSuccess {Datetime} created_at  Order datetime created.
     * @apiSuccess {Datetime} updated_at Order datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "order_id": 1764,
     *         "order_status": "paid",
     *         "customer_name": "Timothy Miller",
     *         "customer_address": "697 Leuschke Row\nJalonland, MN 59100-3392",
     *         "customer_phone": "(313)004-4248",
     *         "fulfillment_status": "0",
     *         "tracking_number": null,
     *         "distribution_center_id": 1821,
     *         "order_items": [
     *           {
     *             "item_id": 3429,
     *             "sku_id": "1817",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "2196102365838",
     *             "barcode": "6692589714566",
     *             "order_quantity": "4",
     *             "original_quantity": "4",
     *             "options": [
     *               {
     *                 "sku_id": "1817",
     *                 "option_name": "Colour",
     *                 "option_value": "fuchsia"
     *               }
     *             ]
     *           },
     *           {
     *             "item_id": 3430,
     *             "sku_id": "1818",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "9446857039069",
     *             "barcode": "3325125300628",
     *             "order_quantity": "9",
     *             "original_quantity": "9",
     *             "options": [
     *               {
     *                 "sku_id": "1818",
     *                 "option_name": "Colour",
     *                 "option_value": "navy"
     *               }
     *             ]
     *           }
     *         ],
     *         "created_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "updated_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         }
     *    }
     *  
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "tracking_number": [
     *             "The tracking number field is required when order status is shipped."
     *           ]
     *         }
     *       }
     */
    public function update_order(PartnerRepositoryContract $partnerRepo, Request $request, $order_id)
    {
        $order = $partnerRepo->updateOrder($order_id, $request->all(), $this->partner_id);
        return response()->json($order);
    }
    /**
     * @api {post} /distribution_centers/orders/:order_id/return Return order 
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} order_id Order Unique Id.
     * @apiVersion 1.0.0
     * @apiName ReturnOrder
     * @apiGroup Orders
     * @apiParam {Array} return_items <code>Required</code> Items array.
     * @apiParam {Integer} return_items.item_id <code>Required</code> Order Item Unique Id.
     * @apiParam {Integer} return_items.quantity <code>Required</code> Returned quantity.
     * @apiParamExample {json} Request-Example:
     *     {
     *           "return_items": [
     *                   {
     *                       "item_id": 3429,
     *                       "quantity": 1
     *                   }
     *               ]
     *       }
     *
     * @apiSuccess {Integer} order_id Order Unique Id.
     * @apiSuccess {String} order_status  Status of the order.
     * @apiSuccess {String} customer_name  Customer full name.
     * @apiSuccess {Address} customer_address  Customer shipping address.
     * @apiSuccess {Phone} customer_phone  Customer contact phone number.
     * @apiSuccess {Integer} fulfillment_status  Fulfillment status of the order.
     * @apiSuccess {Number} tracking_number  Shipping tracking number.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Array} order_items  Order Items array.
     * @apiSuccess {Integer} order_items.item_id Unique Id for an item for the order.
     * @apiSuccess {Integer} order_items.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.product_name  SKU/Product name.
     * @apiSuccess {Integer} order_items.hubwire_sku  Hubwire SKU/Product Unique Id.
     * @apiSuccess {String} order_items.barcode  SKU/Product barcode.
     * @apiSuccess {Integer} order_items.order_quantity  Order Item quantity.
     * @apiSuccess {Integer} order_items.original_quantity  Order Item original quantity.
     * @apiSuccess {Array} order_items.options  SKU/Product options or variantion type.
     * @apiSuccess {Integer} order_items.options.sku_id  SKU/Product Unique Id.
     * @apiSuccess {String} order_items.options.option_name  Option name e.g. Colour, Size, Material etc.
     * @apiSuccess {String} order_items.options.option_value  The option value e.g. White, M, Cotton etc.
     * @apiSuccess {Datetime} created_at  Order datetime created.
     * @apiSuccess {Datetime} updated_at Order datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "order_id": 1764,
     *         "order_status": "paid",
     *         "customer_name": "Timothy Miller",
     *         "customer_address": "697 Leuschke Row\nJalonland, MN 59100-3392",
     *         "customer_phone": "(313)004-4248",
     *         "fulfillment_status": "0",
     *         "tracking_number": null,
     *         "distribution_center_id": 1821,
     *         "order_items": [
     *           {
     *             "item_id": 3429,
     *             "sku_id": "1817",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "2196102365838",
     *             "barcode": "6692589714566",
     *             "order_quantity": "3",
     *             "original_quantity": "4",
     *             "options": [
     *               {
     *                 "sku_id": "1817",
     *                 "option_name": "Colour",
     *                 "option_value": "fuchsia"
     *               }
     *             ]
     *           },
     *           {
     *             "item_id": 3430,
     *             "sku_id": "1818",
     *             "product_name": "Adipisci quibusdam dolor blanditiis dolor nam et.",
     *             "hubwire_sku": "9446857039069",
     *             "barcode": "3325125300628",
     *             "order_quantity": "9",
     *             "original_quantity": "9",
     *             "options": [
     *               {
     *                 "sku_id": "1818",
     *                 "option_name": "Colour",
     *                 "option_value": "navy"
     *               }
     *             ]
     *           }
     *         ],
     *         "created_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "updated_at": {
     *           "date": "2016-02-01 04:30:56",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         }
     *    }
     *  
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "return_items.0.item_id": [
     *             "The selected return items.0.item id is invalid."
     *           ],
     *           "return_items.0.quantity": [
     *             "The return items.0.quantity must be at least 1."
     *           ]
     *         }
     *       }
     */
    public function return_order(PartnerRepositoryContract $partnerRepo, Request $request, $order_id)
    {
        $order = $partnerRepo->returnOrder($order_id, $request->all(), $this->partner_id);
        return response()->json($order);
    }

    public function cancel_order($order_id)
    {
    }
    /**
     * @api {post} /distribution_centers/:dc_id/webhooks Install a webhook 
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiVersion 1.0.0
     * @apiName InstallWebhook
     * @apiGroup Webhooks
     * @apiParam {String} event <code>Required</code> Valid values 'orders/created','orders/updated','transfers/approved','replenishments/approved'.
     * @apiParam {Url} url <code>Required</code> Your callback URL that you want the system send information about the event to.
     * @apiParamExample {json} Request-Example:
     *     {
     *           "event" : "orders/created",
     *           "url" : "http://www.myapps.com/order/create"
     *     }
     *
     * @apiSuccess {Integer} webhook_id Webhook Unique Id.
     * @apiSuccess {String} event  Event name. Valid values 'orders/created','orders/updated','transfers/approved','replenishments/approved'.
     * @apiSuccess {Url} url  Callback URL.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Datetime} created_at  Webhook datetime created.
     * @apiSuccess {Datetime} updated_at Webhook datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "webhook_id": 1170,
     *         "event": "orders/created",
     *         "created_at": {
     *           "date": "2016-02-22 02:50:23",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *        },
     *         "updated_at": {
     *           "date": "2016-02-22 02:50:23",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "url": "http://www.myapps.com/orders/create",
     *         "distribution_center_id": "1821"
     *       }
     *  
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "event": [
     *             "The event field is required."
     *           ],
     *           "url": [
     *             "The url field is required."
     *           ]
     *         }
     *       }
     */
    public function install_webhook(
        PartnerRepositoryContract $partnerRepo,
        ChannelRepositoryContract $channelRepo,
        Request $request, $dc_id)
    {
        $dc = $partnerRepo->getDistributionCenterDetails($dc_id, $this->partner_id, ['distribution_ch_id']);
        $response = $channelRepo->install_webhook($dc->distribution_ch_id, $request->all());
        $response->distribution_center_id = $dc_id;
        return response()->json($response);
    }
    /**
     * @api {post} /distribution_centers/:dc_id/webhooks/:webhook_id Update a webhook 
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiParam {Integer} webhook_id Webhook Unique Id.
     * @apiVersion 1.0.0
     * @apiName UpdateWebhook
     * @apiGroup Webhooks
     * @apiParam {String} event <code>Required</code> Event name. Valid values 'orders/created','orders/updated','transfers/approved','replenishments/approved'.
     * @apiParam {Url} url <code>Required</code> Your callback URL that you want the system send information about the event to.
     * @apiParamExample {json} Request-Example:
     *     {
     *           "event" : "orders/updated",
     *           "url" : "http://www.myapps.com/order/update"
     *     }
     *
     * @apiSuccess {Integer} webhook_id Webhook Unique Id.
     * @apiSuccess {String} event  Event name. Valid values 'orders/created','orders/updated','transfers/approved','replenishments/approved'.
     * @apiSuccess {Url} url  Callback URL.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     * @apiSuccess {Datetime} created_at  Webhook datetime created.
     * @apiSuccess {Datetime} updated_at Webhook datetime last updated.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "webhook_id": 1170,
     *         "event": "orders/updated",
     *         "created_at": {
     *           "date": "2016-02-22 02:50:23",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "updated_at": {
     *           "date": "2016-02-22 04:08:47",
     *           "timezone_type": 3,
     *           "timezone": "UTC"
     *         },
     *         "url": "http://www.myapp.com/orders/update",
     *         "distribution_center_id": "1821"
     *       }
     *  
     *
     * @apiErrorExample {json} Error-Required:
     *     HTTP/1.1 200 OK
     *     {
     *         "code": 422,
     *         "error": {
     *           "event": [
     *             "The event field is required."
     *           ],
     *           "url": [
     *             "The url field is required."
     *           ]
     *         }
     *       }
     */
    public function update_webhook(
        ChannelRepositoryContract $channelRepo,
        Request $request, $dc_id, $wh_id)
    {
        $response = $channelRepo->update_webhook($wh_id, $request->all());
        $response->distribution_center_id = $dc_id;
        return response()->json($response);
    }
    /**
     * @api {get} /distribution_centers/:dc_id/webhooks Webhook index
     * @apiDescription The API description 
     *
     * @apiUse myHeader
     * @apiParam {Integer} dc_id Distribution Center Unique Id.
     * @apiVersion 1.0.0
     * @apiName GetWebhooks
     * @apiGroup Webhooks
     *
     * @apiSuccess {Array} webhooks Webhooks array.
     * @apiSuccess {Integer} webhooks.webhook_id Webhook Unique Id.
     * @apiSuccess {String} webhooks.event  Event name. Valid values 'orders/created','orders/updated','transfers/approved','replenishments/approved'.
     * @apiSuccess {Url} webhooks.url  Callback URL.
     * @apiSuccess {Datetime} webhooks.created_at  Webhook datetime created.
     * @apiSuccess {Datetime} webhooks.updated_at Webhook datetime last updated.
     * @apiSuccess {Integer} distribution_center_id  Distribution Center Unique Id.
     *
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *    {
     *         "webhooks": [
     *           {
     *             "webhook_id": 858,
     *             "event": "orders/created",
     *             "created_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "updated_at": {
     *               "date": "2016-02-01 04:30:56",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "url": "http://requestb.in/1de80jr1"
     *           },
     *           {
     *             "webhook_id": 1170,
     *             "event": "orders/updated",
     *             "created_at": {
     *               "date": "2016-02-22 02:50:23",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "updated_at": {
     *               "date": "2016-02-22 04:08:47",
     *               "timezone_type": 3,
     *               "timezone": "UTC"
     *             },
     *             "url": "http://www.myapp.com/orders/update"
     *           }
     *         ],
     *         "distribution_center_id": "1821"
     *       }
     *  
     *
     */

    public function webhooks(PartnerRepositoryContract $partnerRepo,
        ChannelRepositoryContract $channelRepo, $dc_id)
    {
        $dc = $partnerRepo->getDistributionCenterDetails($dc_id, $this->partner_id, ['distribution_ch_id']);
        $response = Cache::remember('webhooks'.$dc_id, env('CACHE_TIME'), function () use ($channelRepo, $dc) {
            return $channelRepo->get_webhooks($dc->distribution_ch_id);
        });
        $response->distribution_center_id = $dc_id;
        return response()->json($response);
    }
}
