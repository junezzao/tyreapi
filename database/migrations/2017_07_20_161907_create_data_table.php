<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sheet_id')->unsigned();
            $table->integer('line_number');
            $table->date('jobsheet_date')->nullable();
            $table->string('jobsheet_no', 50)->nullable();
            $table->string('inv_no', 50)->nullable();
            $table->float('inv_amt', 10, 2)->nullable();
            $table->enum('jobsheet_type', ['Yard', 'Breakdown'])->nullable();
            $table->string('customer_name', 50)->nullable();
            $table->string('truck_no', 50)->nullable();
            $table->string('pm_no', 50)->nullable();
            $table->string('trailer_no', 50)->nullable();
            $table->bigInteger('odometer')->nullable();
            $table->integer('position')->nullable();
            $table->enum('in_attr', ['NT', 'STK', 'USED', 'COC'])->nullable();
            $table->float('in_price', 10, 2)->nullable();
            $table->string('in_size', 50)->nullable();
            $table->string('in_brand', 50)->nullable();
            $table->string('in_pattern', 50)->nullable();
            $table->string('in_retread_brand', 50)->nullable();
            $table->string('in_retread_pattern', 50)->nullable();
            $table->string('in_serial_no', 50)->nullable();
            $table->string('in_job_card_no', 50)->nullable();
            $table->text('out_reason')->nullable();
            $table->string('out_size', 50)->nullable();
            $table->string('out_brand', 50)->nullable();
            $table->string('out_pattern', 50)->nullable();
            $table->string('out_retread_brand', 50)->nullable();
            $table->string('out_retread_pattern', 50)->nullable();
            $table->string('out_serial_no', 50)->nullable();
            $table->string('out_job_card_no', 50)->nullable();
            $table->float('out_rtd', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sheet_id')->references('id')->on('data_sheet');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('data');
    }
}
