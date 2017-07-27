<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStockCorrectionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_correction', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('do_id');
            $table->unsignedInteger('sku_id');
            $table->integer('quantity');
            $table->string('remarks');
            $table->unsignedInteger('user_id');
            $table->timestamp('corrected_at');
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
        Schema::disableForeignKeyConstraints();
        Schema::drop('stock_correction');
        Schema::enableForeignKeyConstraints();
    }
}
