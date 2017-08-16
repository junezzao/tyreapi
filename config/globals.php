<?php
return [
    'status_code' => [
        'NOT_AVAILABLE_ERROR' => 501,
        'SERVER_ERROR'        => 500, // Unexpected Exception encountered / try catch error
        'VALIDATION_ERROR'    => 412, // Validation
        'STANDARD_ERROR'      => 300,
        'DATA_ERROR'          => 404,
        'OK_STATUS'           => 200,
        'UNKNOWN'             => 999,
        'OAUTH_ERROR'         => 400,
    ],

    'date_format'           => 'd-m-Y',
    'datetime_format'       => 'd-m-Y h:i A',

    'format' => [
        'date' => 'd/m/Y'
    ],
];
