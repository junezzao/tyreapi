<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateThirdPartySyncLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('third_party_sync_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sync_id')->unsigned();
            $table->foreign('sync_id')->references('id')->on('third_party_sync')->onDelete('cascade');
            $table->string('request_id', 100)->nullable();
            $table->string('status', 100);
            $table->text('remarks');
            $table->timestamp('sent_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('third_party_sync_log');
    }
}
