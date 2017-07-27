<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBrandsIdOnProductsDuplicatesBrandsOnSameMerchant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            update products p join (
                select d.id as old_id, rw.id new_id from (
                        select * from brands 
                        where prefix in 
                        (
                            select prefix from (select prefix,count(prefix) c 
                            from brands 
                            group by merchant_id,prefix
                            having c>1 order by c desc) a
                        ) 
                        and deleted_at is not null
                ) d
                join (
                    select * from brands 
                        where prefix in 
                        (
                            select prefix from (select prefix,count(prefix) c 
                            from brands 
                            group by merchant_id,prefix
                            having c>1 order by c desc) a
                        ) 
                        and deleted_at is null
                ) rw on rw.prefix = d.prefix and rw.merchant_id = d.merchant_id
            ) b on p.brand_id = b.old_id 
            set p.brand_id = b.new_id;");
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
