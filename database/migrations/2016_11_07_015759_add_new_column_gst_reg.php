<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewColumnGstReg extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('issuing_companies', function (Blueprint $table) {
             $table->boolean('gst_reg')->default(true)->after('address');
             $table->string('gst_reg_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('issuing_companies', function (Blueprint $table) {
             $table->dropColumn('gst_reg');
        });
    }
}
