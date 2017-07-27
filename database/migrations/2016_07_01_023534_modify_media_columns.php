<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyMediaColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('media', function ($table) {
            $table->string('media_url', 255)->change();
            $table->string('media_key', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('media', function ($table) {
            $table->string('media_url', 100)->change();
            $table->string('media_key', 100)->change();
        });
    }
}
