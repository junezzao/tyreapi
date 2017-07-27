<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Admin\Product;

use Es;

class updateProdToEs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:updateProd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recreate index for products and upload their info to elasticsearch';

    protected $index = 'products';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->index = env('ELASTICSEARCH_INDEX','products');
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
            $this->createMappingProductLevel();
            $this->migrate();    
            $this->info('Finished');
    }

    public function migrate()
    { 
        Product::with('media','Essku_in_channel','default_media','brand','tags','merchant','category')->chunk(1000, function($products){
            foreach($products as $product)
            {   
                $product = $product->toArray();
                $this->info('Migrating '.$product['name'].'...');
                Product::insertProduct($product);
            }
        });
    } 

    public function createMappingProductLevel(){
        //products/product
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
        $myTypeMapping = array(
            '_source' => array(
                'enabled' => true
            ),
            'properties' => array(
                'id' => array('type' => 'double'),
                'client_id' => array('type' => 'double'), 
                'merchant_id' => array('type' => 'double'), 
                'name' => array(
                    'type' => 'string',
                    'fields' => array(
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed'
                        )
                    )
                ),
                'merchant_name' => array(
                    'type' => 'string',
                    'fields' => array(
                        'raw' => array(
                            'type' => 'string',
                            'index' => 'not_analyzed'
                        )
                    )
                ),
                'description' => array('type' => 'string'),
                'created_at' => array('type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'),
                'updated_at' => array('type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'),
                'description2' => array('type' => 'string'),
                'default_media' => array(
                    'properties' => array( 
                        'media_id' => array('type' => 'double'),
                        'media_path' => array('type' => 'string')
                    )
                ),
                'media' => array(
                    'properties' => array( 
                        'media_id' => array('type' => 'double'),
                        'media_path' => array('type' => 'string')
                    )
                ),  
                'sku_in_channel' => array(
                    'type' => 'nested',
                    'properties' => array(
                        'channel_sku_id' => array('type' => 'double'),
                        'channel_id' => array('type' => 'double'),
                        'sku_id' => array('type' => 'double'),
                        'channel_sku_active' => array('type' => 'string'),
                        'ref_id' => array('type' => 'double'),
                        'channel_sku_quantity' => array('type' => 'double'),
                        'channel_sku_price' => array('type' => 'double'),
                        'channel_sku_promo_price' => array('type' => 'double'),
                        'channel_sku_coordinates' => array('type' => 'string'),
                        'shared_quantity' => array('type' => 'double'),
                        'channel'=>array( 
                            'properties'=> array(
                                'channel_id' => array('type' => 'double'),
                                'channel_name' => array('type' => 'string'),
                                'channel_type' => array('type' => 'string'),
                                'channel_web' => array('type' => 'string')
                                )
                        ),
                        'sku' => array(
                            'properties' => array( 
                                'sku_id' => array('type' => 'double'),
                                'sku_supplier_code' => array('type' => 'string','analyzer'=>'myAnalyzer'),
                                'sku_weight' => array('type' => 'string'),
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
                                'created_at' => array('type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'),
                                'updated_at' => array('type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss'),
                                'batch' => array(
                                    'properties' => array(
                                        'batch_id' => array('type' => 'double'),
                                        'batch_date' => array('type' => 'date','format' => 'yyyy-MM-dd HH:mm:ss')
                                    )
                                )
                            )
                        ),
                        'options' => array(
                            'properties' => array(
                                'option_name' => array('type' => 'string'),
                                'option_value' => array('type' => 'string','index'=>'not_analyzed')
                            )
                        )

                    ),
                    // 'include_in_root' => 'true',
                ), /** Channel In Sku ends **/
                'brand' => array(
                    'properties' => array( 
                        'brand_id' => array('type' => 'double'),
                        'name' => array(
                            'type' => 'string',
                            'fields' => array(
                                'raw' => array(
                                    'type' => 'string',
                                    'index' => 'not_analyzed'
                                )
                            )
                        ),
                        'prefix' => array('type' => 'string')
                    )
                )
                ,
                'tags' => array(
                    'properties' => array(
                        'tag_id' => array('type' => 'double'),
                        'value' => array('type' => 'string','index'=>'not_analyzed')
                    ) 
                )
            ));    
        

        $indexParams['body']['mappings']['inventory'] = $myTypeMapping;
        
        // Create the index
        $result = Es::indices()->create($indexParams);  
        
    }
}
