<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStorefrontsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('storefronts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('channel_id')->unsigned()->unique();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->string('ipay_merchant_code', 60);
            $table->string('ipay_key', 60);
            $table->string('paypal_email', 60);
            $table->string('paypal_token', 60);
            $table->string('facebook_app_id', 100);
            $table->string('facebook_app_secret', 100);
            $table->text('google_analytics')->nullable();
            $table->string('channel_title', 100);
            $table->string('channel_description', 100);
            $table->string('channel_keyword', 100);
            $table->integer('channel_template');

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
        Schema::dropIfExists('storefronts');
    }
}
