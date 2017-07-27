<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->renameColumn('product_id', 'id');
            $table->renameColumn('product_name', 'name');
            $table->renameColumn('product_desc', 'description');
            $table->renameColumn('product_desc_2', 'description2');
            $table->renameColumn('product_brand', 'brand');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function ($table) {
            $table->renameColumn('id', 'product_id');
            $table->renameColumn('name', 'product_name');
            $table->renameColumn('description', 'product_desc');
            $table->renameColumn('description2', 'product_desc_2');
            $table->renameColumn('brand', 'product_brand');
        });
    }
}
