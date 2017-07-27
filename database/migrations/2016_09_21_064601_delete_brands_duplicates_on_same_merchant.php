<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeleteBrandsDuplicatesOnSameMerchant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            update brands set deleted_at = updated_at
            where id in (
                select id from (
                    select * from brands 
                    where prefix in 
                    (
                        select prefix from (select prefix,count(prefix) c 
                        from brands where deleted_at is null group by merchant_id,prefix
                        having c>1 order by c desc) a
                    ) 
                    and deleted_at is null
                ) aa where created_at = (
                        select max(created_at) from (
                            select * from brands 
                            where prefix in 
                            (
                                select prefix from (select prefix,count(prefix) c 
                                from brands where deleted_at is null group by merchant_id,prefix
                                having c>1 order by c desc) b
                            ) and deleted_at is null
                        ) bb
                     where bb.prefix = aa.prefix and bb.merchant_id = aa.merchant_id
                )
            );
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
