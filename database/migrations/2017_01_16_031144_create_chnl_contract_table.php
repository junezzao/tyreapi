<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChnlContractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('channel_contracts', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->integer('channel_id')->unsigned()->index();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->integer('merchant_id')->unsigned()->index();
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->integer('brand_id')->unsigned()->index();
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->float('guarantee',8,3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('channel_contract_rules', function (Blueprint $table) {
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
            $table->text('products');
            $table->boolean('min_guarantee');
            $table->boolean('fixed_charge');
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
        Schema::dropIfExists('channel_contracts');
        Schema::dropIfExists('channel_contract_rules');
        Schema::enableForeignKeyConstraints();
    }
}
