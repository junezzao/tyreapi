<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Elasticsearch\Client;

class EsOrderTpOrderIdToString extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $indexParams['index']  = env('ELASTICSEARCH_ORDERS_INDEX','orders');
        $indexParams['type']  = 'sales';   
        $indexParams['ignore_conflicts'] = true;
        // Example Index Mapping
        $myTypeMapping = [
                'properties' => [
                    'tp_order_id'  =>  ['type' => 'string', 'store' => true]
                ]
        ];
        $indexParams['body'] =  $myTypeMapping;
        // Create the index
        $client = new Client();
        $result = $client->indices()->putMapping($indexParams);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
