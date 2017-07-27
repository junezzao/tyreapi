<?php 

namespace App\Modules\ThirdParty\Mappers;

/**
 * Eleven Street Mapper
 * This class helps in mapping Hubwire fields with ElevenSt fields
 *
 * @version   1.0
 * @author    Yuki AY <yuki@hubwire.com>
 */

class ElevenStMapper
{
    private static $mapping = array(
        'prdNo'                 => 'product_ref_id',
        'selMthdCd'             => 'sell_method',
        'sellerPrdCd'           => 'product_code',
        'dispCtgrNo'            => 'thirdparty_category',
        'prdTypCd'              => 'service_type',
        'prdNm'                 => 'product_name',
        'prdStatCd'             => 'item_condition',
        'prdWght'               => 'product_weight',
        'htmlDetail'            => 'product_description',
        'selTermUseYn'          => 'sales_period',
        'selPrc'                => 'product_price',
        'prdSelQty'             => 'product_quantity',
        'minorSelCnYn'          => 'allow_minors',
        'advrtStmt'             => 'short_description',
        'asDetail'              => 'after_service_note',
        'rtngExchDetail'        => 'return_exchange_tnc',
        'optSelCnt'             => 'option_selection_count',
        'colTitle'              => 'option_title',
        'dlvMthCd'              => 'shipping_method',
        'dlvCstInstBasiCd'      => 'delivery_type',
        'suplDtyfrPrdClfCd'     => 'gst_applicable',
        'suplDtyfrPrdClfRate'   => 'gst_rate',
        'reviewDispYn'          => 'display_reviews',
        'reviewOptDispYn'       => 'enable_reviews'
    );

    public static function getHubwireField($key)
    {
        if(isset(self::$mapping[$key]))
        {
            return self::$mapping[$key];
        }
        else
        {
            return null;
        }
    }

    public static function mapToHubwireFields($product)
    {
        $new_product = self::mapFieldNames($product, self::$mapping);
        return $new_product;
    }

    public static function mapToMarketplaceFields($product)
    {
        $rev_arr = array_flip(self::$mapping);
        $new_product = self::mapFieldNames($product,$rev_arr);
        return $new_product;
    }
    
    public static function mapFieldNames($product , $map_arr)
    {
        $new_product = array();
        foreach ($product as $field => $value) {
            if(isset($map_arr[$field]))
            {
                $new_product[$map_arr[$field]] = $value;
            }else{
                $new_product[$field] = $value;
            }
        }
        return $new_product;
    }
}