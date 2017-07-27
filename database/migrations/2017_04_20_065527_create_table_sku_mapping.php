<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableSkuMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sku_mapping', function(Blueprint $table)
        {  
            $table->increments('id');
            $table->integer('sku_id');
            $table->string('old_hubwire_sku');
            $table->string('new_hubwire_sku');
            $table->integer('new_sku_id')->nullable();
            $table->dateTime('completed_at')->nullable();
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
        Schema::drop('sku_mapping');
    }
}
