<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGtoManifestItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gto_manifest_items', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('gto_id')->unsigned();
            $table->integer('sku_id')->unsigned();
            $table->integer('quantity')->unsigned();
            $table->integer('picked')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('gto_manifest_items');
    }
}
