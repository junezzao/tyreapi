<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelSKU;

class MigrateEsSalesPeriodToChannelSku extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channelParams = [
            'size' => 9000,
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_INDEX','channels'),
            'type' => 'settings'
        ];

        $skuParams = [
            'size' => 9000,
            'index' => env('ELASTICSEARCH_CUSTOM_FIELDS_DATA_INDEX','channel_sku'),
            'type' => 'data'
        ];

        // Migrate Start Date
        $params = null;
        $params[] = [ 'match' => ['field_name' => 'SaleStartDate'] ];
        $params[] = [ 'match' => ['field_name' => 'cupnIssStartDy'] ];
        
        $channelParams['body']['query']['bool']['should'] = $params;
        $channelParams['body']['query']['bool']['minimum_should_match'] = 1;
        
        $matched = json_decode( json_encode( \Es::search($channelParams) ) ); 
        
        $fields = $matched->hits->hits;

        foreach($fields as $field)
        {
            $id = $field->_id; // the custom field id 
            
            $params = null;
            $params[] = [ 'match' => ['custom_field_id' => $id] ];
            
            $skuParams['body']['query']['bool']['should'] = $params;
            $skuParams['body']['query']['bool']['minimum_should_match'] = 1;
            
            // search for custom field that match the custom_field_id
            $found = json_decode( json_encode( \Es::search($skuParams) ) ); 
            
            $custom_fields = $found->hits->hits;
            
            foreach($custom_fields as $cf)
            {
                // Save the value to Channel SKU (mysql)
                $channel_sku = ChannelSKU::find($cf->_source->channel_sku_id);

                if(is_null($channel_sku)) continue;

                $channel_sku->promo_start_date = $cf->_source->field_value;
                $channel_sku->save();
            }

        }

        // Migrate End Date
        $params = null;
        $params[] = [ 'match' => ['field_name' => 'SaleEndDate'] ];
        $params[] = [ 'match' => ['field_name' => 'cupnIssEndDy'] ];
        
        $channelParams['body']['query']['bool']['should'] = $params;
        $channelParams['body']['query']['bool']['minimum_should_match'] = 1;
        
        $matched = json_decode( json_encode( \Es::search($channelParams) ) ); 
        
        $fields = $matched->hits->hits;

        foreach($fields as $field)
        {
            $id = $field->_id; // the custom field id 
            
            $params = null;
            $params[] = [ 'match' => ['custom_field_id' => $id] ];
            
            $skuParams['body']['query']['bool']['should'] = $params;
            $skuParams['body']['query']['bool']['minimum_should_match'] = 1;
            
            // search for custom field that match the custom_field_id
            $found = json_decode( json_encode( \Es::search($skuParams) ) ); 
            
            $custom_fields = $found->hits->hits;
            
            foreach($custom_fields as $cf)
            {
                // Save the value to Channel SKU (mysql)
                $channel_sku = ChannelSKU::find($cf->_source->channel_sku_id);

                if(is_null($channel_sku)) continue;

                $channel_sku->promo_end_date = $cf->_source->field_value;
                $channel_sku->save();
            }
            
        }
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
