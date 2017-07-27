<?php

return array(
    'marketplace_timezone' => 'Asia/Kuala_Lumpur',
    'num_of_option' => 1,
    'image_limit'=> 4,
    'queryStr_date_format' => 'dmYHi',
    'sales_status'=>array(
        '101'=> 'Order Complete',
        '102'=> 'Awaiting Payment',
        '103'=> 'Awaiting Pre-order',
        '201'=> 'Pre-order Payment Complete',
        '202'=> 'Payment Complete',
        '301'=> 'Preparing for Shipment',
        '401'=> 'Shipping in Progress',
        '501'=> 'Shipping Complete',
        '601'=> 'Claim Requested',
        '701'=> 'Cancellation Requested',
        '801'=> 'Awaiting for Re-approval',
        '901'=> 'Purchase Confirmed',
        'A01'=> 'Return Complete',
        'B01'=> 'Order Cancelled',
        'C01'=> 'Cancel Order upon Purchase Confirmation',
        ),
    'default' => array(
        'shipping_method'       => '01',
        'shipping_provider'     => '10004',
    ),
    'product_default' => array(
        'sell_method'           => '01',
        'service_type'          => '01',
        'item_condition'        => '01',
        'allow_minors'          => 'Y',
        'gst_applicable'        => '01',
        'origin_country'        => '01',
        // 'display_reviews'    => 'N',
        // 'enable_reviews'     => 'N',
        'after_service_note'    => '--',
        'return_exchange_tnc'   => '--',
        'sales_period'          => 'N',
        'shipping_method'       => '01',
        'delivery_type'         => '01',
    ),
    'shipping_method' => array(
        'Courier'                   => '01',
        'Direct Shipping'           => '05',
        'Shipping not applicable'   => '99',
    ),
    'shipping_provider' => array(
        'Pos Laju'  => '10001',
        'GDEX'      => '10002',
        'TA-Q-BIN'  => '10003',
        'Skynet'    => '10004', 
        'DHL'       => '10007',
        'FedEx'     => '10008',
        'etc'       => '30001',
    ),
    'field_options' => array(
        'sell_method' => array(
            'ready_stock' => '01',
        ),
        'service_type' =>  array(
            'general_product' => '01',
            'e-Voucher' => '02',
        ),
        'item_condition' => array(
            'new' => '01',
        ),
        'allow_minors' => array(
            'yes' => 'Y',
            'no' => 'N',
        ),
        'gst_applicable' => array(
            'standard_rate'=>'01',
            'exempted_rate'=>'02',
            'zero_rate'=>'03',
            'flat_rate'=>'04',
        ),
        'origin_country' => array(
            'domestic' => '01',
            'overseas' => '02',
        ),
        'origin_country_code.domestic' => array(
            "Johor" =>"22",
            "Kedah" =>"11",
            "Kelantan" =>"13",
            "Melaka" =>"21",
            "Negeri Sembilan" =>"20",
            "Pahang" =>"15",
            "Perak" =>"16",
            "Perlis" =>"10",
            "Pulau Pinang" =>"12",
            "Sabah" =>"24",
            "Sarawak" =>"25",
            "Selangor" =>"17",
            "Terengganu" =>"14",
            "Wilayah Persekutuan Kuala Lumpur" =>"18",
            "Wilayah Persekutuan Labuan" =>"23",
            "Wilayah Persekutuan Putra Jaya" =>"19",
        ),
        'origin_country_code.overseas' => array(
            "Aandorra" =>"1383",
            "Afghanistan" =>"1275",
            "Africa" =>"1249",
            "Albania" =>"1384",
            "Algerie" =>"1331",
            "Angola" =>"1332",
            "Antigua and Barbuda" =>"1413",
            "Argentina" =>"1427",
            "Armenia" =>"1273",
            "Asia" =>"1248",
            "Australia" =>"1441",
            "Austria" =>"1387",
            "Azerbaizhan" =>"1274",
            "Bahamas" =>"1407",
            "Bahrain" =>"1263",
            "Bangladesh" =>"1264",
            "Barbados" =>"1406",
            "Belgium" =>"1370",
            "Belize" =>"1408",
            "Belorus" =>"1371",
            "Benin" =>"1319",
            "Bhutan" =>"1266",
            "Bolivia" =>"1424",
            "Bosnia and Herzegovina" =>"1372",
            "Botswana" =>"1320",
            "Brazil" =>"1425",
            "Brunei" =>"1267",
            "Bulgaria" =>"1373",
            "Burkina Faso" =>"1322",
            "Burundi" =>"1321",
            "Cabo Verde" =>"1344",
            "Cambodia" =>"1290",
            "Cameroon" =>"1343",
            "Canada" =>"1417",
            "Central African Republic" =>"1339",
            "Chad" =>"1342",
            "Chile" =>"1430",
            "China" =>"1287",
            "Colombia" =>"1431",
            "Comoros" =>"1346",
            "Costa Rica" =>"1418",
            "Cote d'lvoire" =>"1347",
            "Croatia" =>"1391",
            "Cuba" =>"1419",
            "Czech" =>"1390",
            "Democratic Republic of the Congo" =>"1349",
            "Denmark" =>"1356",
            "Djibouti" =>"1340",
            "Dominica" =>"1403",
            "Dominican Republic" =>"1402",
            "Ecuador" =>"1428",
            "Egypt" =>"1336",
            "El Salvador" =>"1414",
            "Equatorial Guinea" =>"1338",
            "Eritrea" =>"1333",
            "Estonia" =>"1385",
            "Ethiopia" =>"1334",
            "Europe" =>"1250",
            "Fiji" =>"1447",
            "Finland" =>"1397",
            "France" =>"1396",
            "Gabon" =>"1300",
            "Gambia" =>"1301",
            "Germany" =>"1357",
            "Ghana" =>"1299",
            "Greece" =>"1353",
            "Grenada" =>"1400",
            "Gruziya" =>"1254",
            "Guatemala" =>"1399",
            "Guinea Bissau" =>"1303",
            "Guinea" =>"1302",
            "Guyana" =>"1422",
            "Haiti" =>"1412",
            "Honduras" =>"1415",
            "Hongkong" =>"1450",
            "Hungary" =>"1398",
            "Iceland" =>"1381",
            "India" =>"1283",
            "Indonesia" =>"1284",
            "Iran" =>"1281",
            "Iraq" =>"1280",
            "Ireland" =>"1382",
            "Israel" =>"1282",
            "Italy" =>"1389",
            "Jameica" =>"1416",
            "Japan" =>"1285",
            "Jordan" =>"1278",
            "Kazakhstan" =>"1288",
            "Kenya" =>"1345",
            "Kiribati" =>"1442",
            "Kuwait" =>"1291",
            "Kypros" =>"1392",
            "Kyrgyzstan" =>"1292",
            "Laos" =>"1257",
            "Latvia" =>"1358",
            "Lebanon" =>"1258",
            "Lesotho" =>"1309",
            "Liberia" =>"1308",
            "Libya" =>"1311",
            "Liechtenstein" =>"1363",
            "Lituania" =>"1362",
            "Luxembourg" =>"1361",
            "Macedonia" =>"1364",
            "Madagascar" =>"1312",
            "Malawi" =>"1313",
            "Maldives" =>"1260",
            "Mali" =>"1314",
            "Malta" =>"1368",
            "Marshall" =>"1436",
            "Mauritanie" =>"1317",
            "Mauritius" =>"1316",
            "Mexico" =>"1404",
            "Micronesia" =>"1437",
            "Moldova" =>"1367",
            "Monaco" =>"1365",
            "Mongolia" =>"1261",
            "Montenegro" =>"1366",
            "Morocco" =>"1315",
            "Mozambique" =>"1318",
            "Myanmar" =>"1262",
            "Namibia" =>"1304",
            "Nauru" =>"1434",
            "Nepal" =>"1255",
            "Netherlands" =>"1354",
            "New zealand" =>"1435",
            "Nicaragua" =>"1401",
            "Niger" =>"1307",
            "Nigeria" =>"1305",
            "North America" =>"1251",
            "North Korea" =>"1286",
            "Norway" =>"1355",
            "Oceania" =>"1253",
            "Oman" =>"1277",
            "Pakistan" =>"1297",
            "Palau" =>"1446",
            "Panama" =>"1421",
            "Papua New Guinea" =>"1445",
            "Paraguay" =>"1432",
            "Peru" =>"1433",
            "Philippines" =>"1298",
            "Poland" =>"1395",
            "Portugal" =>"1394",
            "Qatar" =>"1289",
            "Republic of South Africa" =>"1306",
            "Republic of the Congo" =>"1348",
            "Rumania" =>"1360",
            "Russia" =>"1359",
            "Rwanda" =>"1310",
            "Saint Kitts and Nevis" =>"1411",
            "Saint Lucia" =>"1409",
            "Saint Vincent and the Grenadines" =>"1410",
            "San Marino" =>"1374",
            "Sao Tome and Principe" =>"1323",
            "Saudi Arabia" =>"1268",
            "Senegal" =>"1325",
            "Serbia" =>"1375",
            "Seychelles" =>"1326",
            "Sierra Leone" =>"1330",
            "Singapore" =>"1271",
            "Slovakia" =>"1379",
            "Slovenia" =>"1380",
            "Solomon Is." =>"1440",
            "Somalia" =>"1327",
            "South America" =>"1252",
            "South Korea" =>"1449",
            "Spain" =>"1378",
            "Sri Lanka" =>"1269",
            "Sudan" =>"1328",
            "Surinam" =>"1426",
            "Swaziland" =>"1329",
            "Sweden" =>"1376",
            "Switzerland" =>"1377",
            "Syria" =>"1270",
            "T aiwan" =>"1294",
            "T anzania" =>"1350",
            "Tadzhikistan" =>"1295",
            "Thailand" =>"1293",
            "Timor-Leste" =>"1256",
            "Togo" =>"1351",
            "Tonga" =>"1443",
            "Trinidad and Tobago" =>"1420",
            "Tunisie" =>"1352",
            "Turkey" =>"1393",
            "Turkmenistan" =>"1296",
            "Tuvalu" =>"1444",
            "Uganda" =>"1335",
            "Ukraina" =>"1388",
            "United Arab Emirates" =>"1272",
            "United Kingdom" =>"1386",
            "United States of America" =>"1405",
            "Uruguay" =>"1429",
            "Uzbekistan" =>"1279",
            "Vanuatu" =>"1438",
            "Vatican" =>"1369",
            "Venezuela" =>"1423",
            "Vietnam" =>"1265",
            "Western Sahara" =>"1324",
            "Western Samoa" =>"1439",
            "Yemen" =>"1276",
            "Zambia" =>"1337",
            "Zimbabwe" =>"1341",
        ),
        'display_reviews' => array(
            'yes' => 'Y',
            'no' => 'N',
        ),
        'enable_reviews' => array(
            'yes' => 'Y',
            'no' => 'N',
        ),
        'sales_period' => array(
            'yes' => 'Y',
            'no' => 'N',
        ),
        'shipping_method' => array(
            'courier_service' => '01',
            'direct_shipping' => '05',
        ),
        'delivery_type' => array(
            'free' => '01',
            'shipping_rate_by_product' => '11',
            'buddle_shipping_fee' => '12',
        ),
        'sales_period_code' => array(0,3,5,7,15,30,60,90,120),
    ),
);
?>
