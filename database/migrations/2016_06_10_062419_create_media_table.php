<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create the media table
        Schema::create('media', function (Blueprint $table) {
            $table->increments('media_id');
            $table->string('filename', 25);
            $table->string('ext', 10);
            $table->string('ref_type', 25)->nullable();
            $table->integer('ref_id')->nullable();
            $table->string('media_url', 100);
            $table->string('media_key', 100);
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
        // Drop the media table
        Schema::drop('media');
    }
}
