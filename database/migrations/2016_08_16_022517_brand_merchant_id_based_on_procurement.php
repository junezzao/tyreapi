<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class BrandMerchantIdBasedOnProcurement extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = DB::select("
            update brands join (
                select * from (
                    select b.id as brand_id, b.prefix,b.name,pb.supplier_id,sp.merchant_id, count(distinct p.id) as total_product  
                    from purchase_items pi
                    LEFT JOIN purchase_batches pb on pb.batch_id = pi.batch_id
                    LEFT JOIN sku on sku.sku_id = pi.sku_id
                    LEFT JOIN products p on p.id = sku.product_id
                    LEFT JOIN brands b on b.id = p.brand_id
                    LEFT JOIN suppliers sp on sp.id = pb.supplier_id
                    GROUP BY b.id , pb.supplier_id
                    having brand_id is not null
                ) a where total_product = 
                (
                    select max(total_product)  from (
                        select b.id as brand_id, b.prefix,b.name,pb.supplier_id,sp.merchant_id, count(distinct p.id) as total_product  
                        from purchase_items pi
                        LEFT JOIN purchase_batches pb on pb.batch_id = pi.batch_id
                        LEFT JOIN sku on sku.sku_id = pi.sku_id
                        LEFT JOIN products p on p.id = sku.product_id
                        LEFT JOIN brands b on b.id = p.brand_id
                        LEFT JOIN suppliers sp on sp.id = pb.supplier_id
                        GROUP BY b.id , pb.supplier_id
                        having brand_id is not null
                    ) b where b.brand_id = a.brand_id
                )
            ) c on c.brand_id = brands.id
            set brands.merchant_id = c.merchant_id
    ");
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
