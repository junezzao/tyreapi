<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBrandsUniques extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function ($table) {
            $conn = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $conn->listTableIndexes('brands');
            $tmp = [];
            foreach ($indexes as $index) {
                $tmp[] = $index->getName();
            }
            
            if (in_array('brands_product_brand_client_id_unique', $tmp)) {
                $table->dropUnique('brands_product_brand_client_id_unique');
                $table->unique(array('prefix', 'client_id'));
            }
            $table->dropUnique('brands_prefix_client_id_unique');
            //$table->unique(array('prefix','merchant_id'));
            $table->tinyInteger('active')->default(1)->after('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brands', function ($table) {
            $table->dropUnique('brands_prefix_merchant_id_unique');
            $table->unique(array('prefix', 'client_id'));
            $table->dropColumn('active');
        });
    }
}
