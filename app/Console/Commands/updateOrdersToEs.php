<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Order;
use Es;

class updateOrdersToEs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:updateOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push orders to ElasticSearch';

    protected $index = 'orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->index = env('ELASTICSEARCH_ORDERS_INDEX','orders');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $indexParams['index']  = $this->index;
        $exists = Es::indices()->exists($indexParams);
        if($exists)
        {
            if ($this->confirm("Index '".$this->index."' already exists. Do you wish to continue? [y|N]")) {
                Es::indices()->delete($indexParams);
            }
            else{
                $this->info('Exiting...');
                return;
            }
        }
        $this->createMapping();
        $this->migrate();    
        $this->info('Finished');
    }

    private function migrate()
    {
        Order::chunk(1000, function($orders){
            foreach($orders as $order)
            {   
                $this->info('Migrating order #'.$order->id);
                $order->updateElasticSearch();
            }
        });
    }

    private function createMapping(){
        $indexParams['index']  = $this->index;

        // Index Settings
        $indexParams['body']['settings']['number_of_shards']   = 1;
        $indexParams['body']['settings']['number_of_replicas'] = 0;
        $indexParams['body']['settings']['analysis']['analyzer']['myAnalyzer'] = array('type'=>'custom','tokenizer'=>'whitespace');
        $indexParams['body']['settings']['analysis']['filter']['word_filter'] = array('type'=>'word_delimiter',
                                                                                      'split_on_numerics'=>'false',
                                                                                      'generate_word_parts'=>'false',
                                                                                      'generate_number_parts'=>'false',
                                                                                      'split_on_case_change'=>'false',
                                                                                      'preserve_original'=>'false',
                                                                                      );
        // Example Index Mapping
        $myTypeMapping = [
                '_source' => [
                    'enabled' => true
                ],
                'properties' => [
                    'tp_order_id'                   =>  ['type' => 'string'],
                    'tp_order_date'                 =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'paid_date'                     =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'reserved_date'                 =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'cancelled_date'                =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'shipped_date'                  =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'created_at'                    =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'updated_at'                    =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'shipping_notification_date'    =>  ['type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'],
                    'items'                         =>  ['properties' => [
                                                            'ref' => ['properties' => [
                                                                'sku' => ['properties'=>[
                                                                    'hubwire_sku' => array(
                                                                        'type' => 'string',
                                                                        'analyzer'=>'myAnalyzer',
                                                                        'fields' => array(
                                                                            'raw' => array(
                                                                                'type' => 'string',
                                                                                'index' => 'not_analyzed'
                                                                            )
                                                                        )
                                                                    ),
                                                                    'sku_supplier_code' => array(
                                                                        'type' => 'string',
                                                                        'analyzer'=>'myAnalyzer',
                                                                        'fields' => array(
                                                                            'raw' => array(
                                                                                'type' => 'string',
                                                                                'index' => 'not_analyzed'
                                                                            )
                                                                        )
                                                                    ),
                                                                    'client_sku' => array(
                                                                        'type' => 'string',
                                                                        'analyzer'=>'myAnalyzer',
                                                                        'fields' => array(
                                                                            'raw' => array(
                                                                                'type' => 'string',
                                                                                'index' => 'not_analyzed'
                                                                            )
                                                                        )
                                                                    )
                                                                ]]
                                                            ]]
                                                        ]]
                ]
        ];

        $indexParams['body']['settings']['ignore_malformed'] = true;
        $indexParams['body']['mappings']['sales'] = $myTypeMapping;

        // Create the index
        $result = Es::indices()->create($indexParams);  
    }
}
