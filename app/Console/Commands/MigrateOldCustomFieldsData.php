<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Es;

class MigrateOldCustomFieldsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:migrateoldcf';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates custom fields data from the old admin to the new admin.';

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
        $cfIds = [];

        // get old custom fields 
        $params = [
            'index' => $this->cfIndex,
            'type' => 'settings',
            'size' => 1000,
            'body' => [
                'query' => [
                    'match_all' => [ ]
                ]
            ]
        ];

        $res = json_decode(json_encode(Es::search($params)));
        foreach ($res->hits->hits as $cf) {     
            if (isset($cf->_source->custom_fields) && !empty($cf->_source->custom_fields)) {
                $channelId = $cf->_source->channel_id;
                foreach($cf->_source->custom_fields as $customField) {

                    // migrate ones that have a field name
                    if (isset($customField->field_name) && $customField->field_name!="") {
                        $newCFParams = [
                            'index' => $this->cfIndex,
                            'type' => 'settings',
                            'body' => array("channel_id"=>$channelId, 
                                            "field_name"=>isset($customField->field_name)?$customField->field_name:'', 
                                            "compulsory"=>isset($customField->compulsory)?$customField->compulsory:'', 
                                            "category"=> isset($customField->category)?$customField->category:'', 
                                            "default_value"=>isset($customField->default_value)?$customField->default_value:''
                            )
                        ];
                        $result = Es::index($newCFParams);
                        $cfIds[$channelId."/".$customField->field_name] = $result["_id"];
                    }
                }
            }
            
        }
        
        // get old custom fields
        $cfdParams = [
            'index' => 'channel_sku',
            'type' => 'data',
            'size' => 1000,
            'body' => [
                'query' => [
                    'match_all' => [ ]
                ]
            ]
        ];

        $response = json_decode(json_encode(Es::search($cfdParams)));
        foreach ($response->hits->hits as $cfd) {
            //dd($cfd);
            $channelId = $cfd->_source->channel_id;
            $channelSkuId = $cfd->_source->channel_sku_id;

            foreach($cfd->_source->custom_fields_data as $customFieldData) {

                // migrate ones that have a field value
                if (isset($customFieldData->field_value) && $customFieldData->field_value!="") {
                    $newCFDParams = [ 'index' => $this->cfdIndex, 'type' => 'data'];
                    $customFieldId = isset($cfIds[$channelId."/".$customFieldData->field_name])?$cfIds[$channelId."/".$customFieldData->field_name]:'';
                    
                    $newCFDParams['body']['channel_sku_id'] = $channelSkuId;
                    $newCFDParams['body']['custom_field_id'] = $customFieldId;
                    $newCFDParams['body']['field_value'] = $customFieldData->field_value;
                    
                    $ret = Es::index($newCFDParams);
                }
            }
        }
    }
}
