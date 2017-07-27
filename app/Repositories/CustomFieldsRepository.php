<?php namespace App\Repositories;

use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exception\HttpResponseException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use Es;
use Monolog;

class CustomFieldsRepository
{
    protected $customLog;
    public function __construct()
    {
        $this->customLog = new Monolog\Logger('Custom Fields Log');
        $this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/custom_fields.log', Monolog\Logger::INFO));

        // $this->userId = Authorizer::getResourceOwnerId();
    }

    // custom fields

    // get custom field by custom field ID
    public function getCfById($cfId) {
        $query = [
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels'),
            'type' => 'settings',
            'size' => 200,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'match' => [
                                    '_id' => $cfId,
                                ],

                            ]
                        ]
                    ]
                ]
            ]
        ];
        $data = json_decode(json_encode(\Es::search($query)));

        $params = [
                'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels'),
                'type' => 'settings',
            ];
        if (!empty($data->hits->hits[0])) {

            $cf = $data->hits->hits[0];
            $params['id'] = $cfId;
            $params['body'] = [
                "channel_id"=>isset($cf->_source->channel_id)?$cf->_source->channel_id:'',
                "field_name"=>isset($cf->_source->field_name)?$cf->_source->field_name:'',
                "compulsory"=>isset($cf->_source->compulsory)?$cf->_source->compulsory:'',
                "default_value"=>isset($cf->_source->default_value)?$cf->_source->default_value:'',
                "category"=>isset($cf->_source->category)?$cf->_source->category:''
            ];
        }

        return $params;

    }

    // get all custom fields in a channel
    public function getCF($channel_id)
    {
        // get all custom fields values and remove custom fields data linked to the fields to be deleted
        $params = [
            'search_type' => 'scan',
            'scroll' => '30s',
            'size' => 50,
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels'),
            'type' => 'settings',
            'body' => [
                'query' => [
                    'match' => [
                        'channel_id' => $channel_id
                    ]
                ]
            ]
        ];

        $data = \Es::search($params);
        $scroll_id = $data['_scroll_id'];
        $customFields = array();

        // Now we loop until the scroll "cursors" are exhausted
        while (\true) {
            // Execute a Scroll request
            $response = \Es::scroll([
                "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
                "scroll" => "30s"           // and the same timeout window
            ]);

            // Check to see if we got any search hits from the scroll
            if (count($response['hits']['hits']) > 0) {

                foreach($response['hits']['hits'] as $cf) {
                    $cf = json_decode(json_encode($cf));

                    $customFields[] = [
                        "id"=>$cf->_id,
                        "field_name"=>isset($cf->_source->field_name)?$cf->_source->field_name:'',
                        "compulsory"=>isset($cf->_source->compulsory)?$cf->_source->compulsory:'',
                        "default_value"=>isset($cf->_source->default_value)?$cf->_source->default_value:'',
                        "category"=>isset($cf->_source->category)?$cf->_source->category:'',
                        "channel_id"=>$cf->_source->channel_id
                    ];
                }

                // Get new scroll_id
                // Must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $response['_scroll_id'];
            } else {
                // No results, scroll cursor is empty.  You've exported all the data
                break;
            }
        }
        return $customFields;
    }

    // return custom fields params
    public function prepareParams()
    {
        $params = [
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels'),
            'type' => 'settings'
        ];

        return $params;
    }

    // return custom fields data params
    public function prepareParamsCFData()
    {
        $params = [
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_DATA_INDEX','channel_sku'),
            'type' => 'data'
        ];

        return $params;
    }

    public function updateCF($channel_id, $data)
    {
        try {
            $changes = $data["changes"];
            //dd($params);
            //$categories = $this->getCategories($this->getChannel($channel_id));
            $allData = $data["data"];
            $newRecord = false;
            foreach($changes as $change) {
                // id of custom field to be changed
                $cfId = $allData[(int)$change[0]][0];
                $params = array();
                //\Log::info($cfId);
                // if creating a new custom field
                if ($cfId == "") {
                    $params = $this->prepareParams();
                    $params['body'] = array("channel_id"=>$channel_id, "field_name"=>"", "compulsory"=>"No", "category"=> "All", "default_value"=>"");
                    $newRecord = true;
                }

                // if changing Mandatory, Default Value, or Category
                else {
                    $params = $this->getCfById($cfId);
                }

                $params['body'][$change[1]] = $change[3];
                $this->customLog->addInfo('Custom fields ' . (($cfId == "") ? 'added' : 'modified') . ' by user '. Authorizer::getResourceOwnerId(), $params);

                $ret = Es::index($params);
            }
            usleep(500000);
            $return['success'] = true;
            $return['message'] =  'Your changes were successfully saved.';
            if ($newRecord)
                $return['data'] = $this->getCF($channel_id);
            return response()->json($return);
        }
        catch(Exception $e)
        {
            \Log::error($e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }

    public function deleteCF($channel_id, $data)
    {
        try {
            $params = $this->prepareParams();
            $idsToRemove = $data["toDelete"];

            foreach($idsToRemove as $key=>$val) {
                $params['id'] = $val[0];

                // remove custom fields
                if (!empty($params['id'])) {
                    $ret = Es::delete($params);
                }
                $this->customLog->addInfo('Custom fields deleted by user ' . Authorizer::getResourceOwnerId(), $params);

                // remove custom fields data
                $cfdParams = $this->prepareParamsCFData();
                $cfdParams['body'] = [
                    'query' => [
                        'match' => [
                            'custom_field_id' => $params['id']
                        ]
                    ]
                ];

                $data = json_decode(json_encode(\Es::search($cfdParams)));

                if (!empty($data->hits->hits)) {
                    $customFields = array();
                    foreach($data->hits->hits as $cf) {
                        $cfdParams = $this->prepareParamsCFData();
                        $cfdParams['id'] = $cf->_id;

                        $res = Es::delete($cfdParams);
                        //\Log::info(print_r($cfdParams,true));
                    }
                }
            }

            $data = $this->getCF($channel_id);

            return response()->json(['success' => true, 'message' => 'The selected fields were successfully deleted.', 'data' => $data]);
        }
        catch(Exception $e)
        {
            \Log::error($e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }


    // custom fields data

    public function getCFData($channel_sku_id)
    {
        $params = [
            'search_type' => 'scan',
            'scroll' => '30s',
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_DATA_INDEX','channel_sku'),
            'type' => 'data',
            'size' => 100,
            'body' => [
                'query' => [
                    'match' => [
                        'channel_sku_id' => intval($channel_sku_id)
                    ]
                ]
            ]
        ];
        $data = Es::search($params);
        $scroll_id = $data['_scroll_id'];
        $customFields = array();

        // Now we loop until the scroll "cursors" are exhausted
        while (\true) {
            // Execute a Scroll request
            $response = \Es::scroll([
                "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
                "scroll" => "30s"           // and the same timeout window
            ]);

            // Check to see if we got any search hits from the scroll
            if (count($response['hits']['hits']) > 0) {

                foreach($response['hits']['hits'] as $cfData) {
                    $cfData = json_decode(json_encode($cfData));

                    $customFields[] = [
                        "id"              => $cfData->_id,
                        "channel_sku_id"  => isset($cfData->_source->channel_sku_id)?$cfData->_source->channel_sku_id:'',
                        "custom_field_id" => isset($cfData->_source->custom_field_id)?$cfData->_source->custom_field_id:'',
                        "field_value"     => isset($cfData->_source->field_value)?$cfData->_source->field_value:''
                    ];
                }

                // Get new scroll_id
                // Must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $response['_scroll_id'];

            } else {
                // No results, scroll cursor is empty.  You've exported all the data
                break;
            }
        }
        return $customFields;
    }

    public function updateCFData($data) {

        $params = $this->prepareParamsCFData();
        if (isset($data['id']))
           $params['id'] = $data['id'];

        $params['body']['channel_sku_id'] = $data['channel_sku_id'];
        $params['body']['custom_field_id'] = $data['custom_field_id'];
        $params['body']['field_value'] = $data['field_value'];

        $ret = Es::index($params);

    }
}
