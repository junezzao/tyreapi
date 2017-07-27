<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->integer('merchant_id')->unsigned()->index();
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->integer('brand_id')->unsigned()->index();
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->string('status');
            $table->float('guarantee',8,3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_rules', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('contract_id')->unsigned()->index();
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->string('type');
            $table->float('type_amount', 8, 3)->nullable();
            $table->string('base');
            $table->string('operand');
            $table->float('min_amount', 8, 2)->nullable();
            $table->float('max_amount', 8, 2)->nullable();
            $table->text('categories');
            $table->text('channels');
            $table->text('products');
            $table->boolean('min_guarantee');
            $table->timestamps();
            $table->softDeletes();
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
        Schema::drop('contracts');
        Schema::drop('contract_rules');
        Schema::enableForeignKeyConstraints();
    }
}
