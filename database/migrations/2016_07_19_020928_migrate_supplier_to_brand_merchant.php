<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateSupplierToBrandMerchant extends Migration
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

            if (in_array('brands_prefix_merchant_id_unique', $tmp)) {
                $table->dropUnique('brands_prefix_merchant_id_unique');
            }
            $table->dropColumn('merchant_id');
        });
        Schema::table('brands', function ($table) {
            $table->integer('merchant_id')->after('client_id')->nullable();
        });


        DB::statement('update brands set merchant_id=NULL');
        DB::statement('update brands left join (select m.id as merchant_id,p.brand_id, sp.supplier_id,b.name as brand_name,b.prefix,sp.supplier_name from sku s join purchase_items pi on pi.sku_id = s.sku_id
                        join purchase_batches pb on pb.batch_id = pi.batch_id
                        join suppliers sp on sp.supplier_id = pb.supplier_id
                        join products p on p.id = s.product_id
                        join brands b on p.brand_id = b.id
                        join merchants m on m.name = sp.supplier_name
                        group by p.brand_id) as source
                        on brands.id = source.brand_id
                        set brands.merchant_id = source.merchant_id');

        Schema::table('brands', function ($table) {
            //$table->unique(array('prefix','merchant_id'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
