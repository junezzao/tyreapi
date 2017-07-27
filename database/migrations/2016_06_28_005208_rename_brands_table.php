<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameBrandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function ($table) {
            $table->renameColumn('brand_id', 'id');
            $table->renameColumn('brand_name', 'name');
            $table->renameColumn('product_brand', 'prefix');
            $table->timestamp('deleted_at')->nullable();
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
            $table->dropColumn('deleted_at');
            $table->renameColumn('prefix', 'product_brand');
            $table->renameColumn('name', 'brand_name');
            $table->renameColumn('id', 'brand_id');
        });
    }
}
