<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Es;

class CustomFieldsElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:customfields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates custom fields indexes for the new admin.';

    protected $cfIndex = 'channels';
    protected $cfdIndex = 'channel_sku';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->cfIndex = env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels');
        $this->cfdIndex = env('ELASTICSEARCH_CUSTOM_FIELDS_DATA_INDEX','channel_sku');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $this->createMappingChannelLevel();
        $this->createMappingChannelSKULevel();
    }

    public function createMappingChannelLevel()
    {   
        //
        $indexParams['index']  = $this->cfIndex;
        
        // Index Settings
        $indexParams['body']['settings']['number_of_shards']   = 1;
        $indexParams['body']['settings']['number_of_replicas'] = 0;
        
        // Custom Fields Index Mapping
        $myTypeMapping = array(
            '_source' => array(
                'enabled' => true
            ),
            /*'_id' => array(
                'index' => "not_analyzed",
                'store' => true
            ),*/
            'properties' => array(
                'channel_id' => array('type' => 'double'),
                'field_name' => array('type' => 'string'),
                'category' => array('type' => 'string'),
                'mandatory' => array('type' => 'string'),
                'default_value' => array('type' => 'string')
            )
        );

        $indexParams['body']['mappings']['settings'] = $myTypeMapping;
        
        // Create the index
        $result = Es::indices()->create($indexParams);
        //dd($result);
    }

    public function createMappingChannelSKULevel()
    {
        //
        $indexParams['index']  = $this->cfdIndex;

        // Index Settings
        $indexParams['body']['settings']['number_of_shards']   = 1;
        $indexParams['body']['settings']['number_of_replicas'] = 0;
        
        // Example Index Mapping
        $myTypeMapping = array(
            '_source' => array(
                'enabled' => true
            ),
            'properties' => array(
                'custom_field_id' => array('type'=>'string'),
                'channel_sku_id' => array('type' => 'double'),
                'field_value' => array('type' => 'string')
            )
        );    

        $indexParams['body']['mappings']['data'] = $myTypeMapping;
        
        // Create the index
        $result = Es::indices()->create($indexParams);
    }
}
