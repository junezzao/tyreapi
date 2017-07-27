<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateThirdPartyReportRemarksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('third_party_report_remarks', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('tp_report_id')->unsigned();
            $table->foreign('tp_report_id')->references('id')->on('third_party_report')->onDelete('cascade');

            $table->integer('added_by')->unsigned()->nullable(); // user_id, 0 = system, thus no foreign constrain added

            $table->text('remarks');

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
        Schema::dropIfExists('third_party_report_remarks');
    }
}
