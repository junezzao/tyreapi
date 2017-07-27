<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateThirdPartyReportLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('third_party_report_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('tp_report_id')->unsigned();
            $table->foreign('tp_report_id')->references('id')->on('third_party_report')->onDelete('cascade');
            $table->string('old_value');
            $table->string('new_value');
            $table->string('field');

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
        Schema::dropIfExists('third_party_report_log');
    }
}
