<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MergeClientAdminHwadminPartnerTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Schema::connection('mysql2')->table('users', function ($table) {
        Schema::table('users', function ($table) {
            $table->string('contact')->after('remember_token')->nullable();
            $table->string('address')->after('contact')->nullable();
            $table->string('timezone')->after('address')->nullable();
            $table->string('currency')->after('timezone')->nullable();
            $table->string('status')->after('currency')->nullable();
            $table->string('old_id')->after('status')->nullable();
            $table->string('category')->after('old_id')->nullable();
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::connection('mysql2')->table('users', function ($table) {
        Schema::table('users', function ($table) {
            $table->dropColumn('contact');
            $table->dropColumn('address');
            $table->dropColumn('timezone');
            $table->dropColumn('currency');
            $table->dropColumn('status');
            $table->dropColumn('old_id');
            $table->dropColumn('category');
            $table->dropColumn('deleted_at');
        });
    }
}
