<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropClientIdForeignKeyOnMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        // orders
        Schema::table('members', function ($table) {
            $table->dropForeign('members_client_id_foreign');
        });
        Schema::enableForeignKeyConstraints();
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        // orders
        Schema::table('members', function ($table) {
            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
        Schema::enableForeignKeyConstraints();
    }
}
