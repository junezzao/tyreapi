<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ProductTag;

class InsertSkuTagsToProductTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_tags', function (Blueprint $table) {
            DB::table('sku_tag')
            ->leftJoin('sku', 'sku.sku_id', '=', 'sku_tag.sku_id')
            ->leftJoin('products', 'products.id', '=', 'sku.product_id')
            ->select('sku.product_id', 'sku_tag.sku_id', 'sku_tag.tag_value', 'sku_tag.created_at', 'sku_tag.updated_at', 'sku_tag.deleted_at', 'sku.deleted_at as sku_deleted_at', 'products.deleted_at as product_deleted_at')
            ->orderBy('sku_tag.created_at') //use sku_tag earliest created date if there is non unique value
            ->chunk(1000, function ($sku_tags) {
                foreach ($sku_tags as $tag) {
                    $arr = [
                        'product_id' => $tag->product_id,
                        'value' => $tag->tag_value,
                    ];
                    $new_tag = ProductTag::firstOrNew($arr);

                    if (!$new_tag->exists) {
                        $new_tag->created_at = $tag->created_at;
                        $new_tag->updated_at = $tag->updated_at;

                        //double check deleted_at dates for products/ sku
                        if (!empty($tag->deleted_at)) {
                            $deleted_at = $tag->sku_deleted_at;
                        } elseif (!empty($tag->sku_deleted_at)) {
                            $deleted_at = $tag->product_deleted_at;
                        } else {
                            $deleted_at = $tag->deleted_at;
                        }
                        $new_tag->deleted_at = $deleted_at;
                        $new_tag->save();
                    } else {
                        if (empty($tag->deleted_at)) {
                            //if any of the tag is not deleted , mark as not deleted

                            $deleted_at = null;
                            $new_tag->deleted_at = $deleted_at;
                            $new_tag->save();
                        }
                    }
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('product_tags')->truncate();
    }
}
