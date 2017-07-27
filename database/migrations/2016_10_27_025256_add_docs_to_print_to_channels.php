<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDocsToPrintToChannels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('channels', function (Blueprint $table) {
            $table->string('docs_to_print')->after('issuing_company');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('docs_to_print');
        });
    }
}
