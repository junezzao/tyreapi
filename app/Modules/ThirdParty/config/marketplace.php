<?php
use Illuminate\Support\Facades\Config;

return array(
    'status_code' => array(
        'NOT_AVAILABLE_ERROR'       => 501,
        'SERVER_ERROR'              => 500, // Unexpected Exception encountered / try catch error
        'VALIDATION_ERROR'          => 412, // Validation
        'STANDARD_ERROR'            => 300,
        'MARKETPLACE_ERROR'         => 411, // MarketPlace Response with Error
        'REQUEST_ERROR'             => 400, // MarketPlace Server Request issues
        'DATA_ERROR'                => 404,
        'OK_STATUS'                 => 200,
        'UNKNOWN'                   => 999
    ),
    'type' => array(
        'CREATE_PRODUCT'       => 1,
        'UPDATE_PRODUCT'       => 2, // Unexpected Exception encountered / try catch error
        'STOCK_QTY_UPDATE'     => 3,
        'GET_ORDER'            => 4
    ),
    'sales_status'=>array(
        'Unpaid' => 'Unpaid',
        'Pending' => 'Pending',
        'Paid' => 'Paid',
        'Failed' => 'Failed',
        'Packing' => 'Packing',
        'Shipped' => 'Shipped',
        'Completed'=>'Completed',
        'Cancelled'=>'Cancelled'
    ),
    'delete_status' =>array(
        'hidden'=>'hidden',
        'deleted'=>'deleted'
    ),
    'image_size'=>array(
        'xl'=> array('width'=>800, 'height'=>1148),
        'lg'=>array('width'=>370, 'height'=>531),
        'md'=>array('width'=>230, 'height'=>330),
        'md-sm'=>array('width'=>160, 'height'=>230),
        'sm'=>array('width'=>110, 'height'=>158),
        'xs'=>array('width'=>55, 'height'=>79),
        'square'=>array('width'=>600, 'height'=>600),
    ),
    'std_date_format' =>'Y-m-d H:i:s',
    'default_timezone' => 'UTC'
);

?>