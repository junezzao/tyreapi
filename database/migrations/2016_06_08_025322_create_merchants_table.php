<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 250);
            $table->string('slug', 20);
            $table->string('address');
            $table->string('contact', 15);
            $table->string('email');
            $table->string('logo_url');
            $table->string('gst_reg_no', 15);
            $table->boolean('self_invoicing');
            $table->string('timezone', 30);
            $table->string('currency');
            $table->string('forex_rate');
            $table->integer('ae');
            $table->string('status');
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
        Schema::dropIfExists('merchants');
    }
}
