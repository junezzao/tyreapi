<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('fees', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('transaction')->unsigned()->default(0);
            $table->double('transaction_fee',8,3)->default(0);
            $table->integer('inbound')->unsigned()->default(0);
            $table->double('inbound_fee',8,3)->default(0);
            $table->integer('outbound')->unsigned()->default(0);
            $table->double('outbound_fee',8,3)->default(0);
            $table->integer('storage')->unsigned()->default(0);
            $table->double('storage_fee',8,3)->default(0);
            $table->integer('shipped')->unsigned()->default(0);
            $table->double('shipped_fee',8,3)->default(0);
            $table->integer('return')->default(0);
            $table->double('return_fee',8,3)->default(0);
            $table->integer('packaging')->unsigned()->default(0);
            $table->double('packaging_fee',8,3)->default(0);
            $table->double('channel',8,3)->default(0);
            $table->integer('contract_id')->unsigned();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
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
       Schema::drop('fees');
    }
}
