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
    'channel_type' => array(
        0   => 'Warehouse',
        1   => 'Online Store',
        2   => 'Marketplace',
        3   => 'Offline Store',
        4   => 'Consignment Counter',
        5   => 'B2B',
        6   => 'Shopify',
        7   => 'Lelong',
        8   => 'Lazada',
        9   => 'Zalora',
        10  => '11Street',
        11  => 'Distribution Center',
        12  => 'Warehouse',
        13  => 'LazadaSC',
        14  => 'Storefront Vendor',
        15  => 'RubberNeck',
        16  => 'BearInBag',
        17  => 'AmaxMall',
        18  => 'Shopee',
        19  => 'Shopify POS',
    ),

    'channel_type_picking_manifest' => array(
        10  => '11Street',
        8   => 'Lazada',
        13  => 'LazadaSC',
        7   => 'Lelong',
        6   => 'Shopify',
        9   => 'Zalora',
        1   => 'Online Store',
        3   => 'Offline Store',
        15  => 'RubberNeck',
        16  => 'BearInBag',
        17  => 'AmaxMall',
    ),

    'date_format'           => 'd-m-Y',
    'date_format_invoice'   => 'd F Y',
    'date_format_drp'       => 'DD-MM-YYYY',
    'date_format_dp'        => 'dd-mm-yyyy',
    'datetime_format'       => 'd-m-Y h:i A',
    'third_party_categories_applicable' => array(
        7   => 'Lelong',
        8   => 'Lazada',
        9   => 'Zalora',
        10  => '11Street',
        13  => 'LazadaSC',
        15  => 'RubberNeck',
        16  => 'BearInBag',
        17  => 'AmaxMall',
    ),

    'format' => [
        'date' => 'd/m/Y'
    ],

    // the default timezone that hubwire operates in
    'hubwire_default_timezone' => 'Asia/Kuala_Lumpur',

    // documents to print for bulk print feature in picking manifest
    'docs_to_print' => array(
        'ORDER_SHEET'           => 0,
        'HW_TAX_INVOICE'        => 1,
        'RETURN_SLIP'           => 2,
        'ZALORA_TAX_INVOICE'    => 3,
    ),

    'pos_laju_csv_headers' => array(
        "Name",
        "Address1",
        "Address2",
        "Postcode",
        "City",
        "State",
        "Country",
        "ContactPerson",
        "PhoneNo",
        "FaxNo",
        "Email",
        "ReferenceNo",
        "Group",
        "Certificate Serial No",
    ),

    'changelog_type_dropdown' => array(
        0 => "Hubwire Admin",
        1 => "Storefront API",
        2 => "Mobile App API",
    ),

    'changelog_type' => array(
        "admin" => 0,
        "storefront" => 1,
        "mobile" => 2,
    ),

    'pos_laju_accounts' => array(
        "Citychemo Manufacturing Sdn Bhd",
    ),

    'changelog_type_dropdown' => array(
        0 => "Hubwire Admin",
        1 => "Storefront API",
        2 => "Mobile App API",
    ),

    'changelog_type' => array(
        "admin"         => 0,
        "storefront"    => 1,
        "mobile"        => 2,
    ),

    'pos_laju_accounts' => array(
        "Citychemo Manufacturing Sdn Bhd",
    ),

    'shipping_provider' => array(
        "GDex",
        "Pos Laju",
        "Skynet",
    ),

    'tp_report_tab_type' => array(
        'PENDING_PAYMENT_TP'         => 0,
        'PENDING_PAYMENT_MERCHANT'   => 1,
        'PAID_MERCHANT'              => 2,
        'COMPLETED'                  => 3,
    ),

    'tax_invoice_file_name' => array(
        0 => "pending_third_party_payment",
        1 => "pending_payment_by_marketplace",
        2 => "paid_by_marketplace",
        3 => "completed",
    ),

    'tp_reports_export_options' => array(
        "Selected",
        "All",
        "Verified",
        "Unverified",
        "Not Found",
        "Tax Invoice Data"
    ),

    'GST' => 1.06,

    'malaysia_region' => array(
        'East Malaysia'     =>  'East Malaysia',
        'West Malaysia'     =>  'West Malaysia',
    ),

    'east_malaysia_state' => array(
        'Sabah'             =>  'Sabah',
        'Sarawak'           =>  'Sarawak',
        'Labuan'            =>  'Labuan',
    ),

    'west_malaysia_state' => array(
        'Perlis'            =>  'Perlis',
        'Kedah'             =>  'Kedah',
        'Kelantan'          =>  'Kelantan',
        'Terengganu'        =>  'Terengganu',
        'Pahang'            =>  'Pahang',
        'Penang'            =>  'Penang',
        'Pulau Pinang'      =>  'Pulau Pinang',
        'Perak'             =>  'Perak',
        'Selangor'          =>  'Selangor',
        'Malacca'           =>  'Malacca',
        'Melaka'            =>  'Melaka',
        'Negeri Sembilan'   =>  'Negeri Sembilan',
        'Johor'             =>  'Johor',
        'Putrajaya'         =>  'Putrajaya',
        'Kuala Lumpur'      =>  'Kuala Lumpur',
        'Wilayah Persekutuan Kuala Lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
        'Wilayah Persekutuan Labuan'       => 'Wilayah Persekutuan Labuan',
        'Wilayah Persekutuan Putra Jaya'   => 'Wilayah Persekutuan Putra Jaya'
    ),

    'accounts' => array(
        '11Street'      =>  '490-100',
        'Online Store'  =>  '490-E00',
        'Shopify'       =>  '490-E00',
        'Lelong'        =>  '490-LE00',
        'Lazada'        =>  '490-L00',
        'LazadaSC'      =>  '490-L00',
        'Shopee'        =>  '490-S00',
        'Zalora'        =>  '490-Z00',
        'Lazada'        =>  '490-L00',
        '13'            =>  '490-FM00',
        '72'            =>  '490-FS00',
        '50'            =>  '490-FC00',
        'Gemfive'       =>  '490-G00',
        'Offline Store' =>  '490-E00',
        ),
    'invoice_account' => '30C-E001',
    'credit_account'  => '510-000',

    'reject_sku' => [
        'reasons' => [
            'Stock out' => 'Stock out',
            'Quantity adjustment' => 'Quantity adjustment',
            'Product Defect' => 'Product Defect'
        ]
    ],
];
