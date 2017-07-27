<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTpReportTableColumnMediaIdToNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->dropForeign('third_party_report_media_id_foreign');\Log::info('1');
        });
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->integer('media_id')->nullable()->change();\Log::info('2');
        });
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->integer('media_id')->change()->unsigned('media_id');
            $table->foreign('media_id')
                ->references('media_id')->on('media')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->dropForeign('third_party_report_media_id_foreign');\Log::info('1');
        });
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->integer('media_id')->nullable(false)->change();\Log::info('2');
        });
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->integer('media_id')->change()->unsigned('media_id');
            $table->foreign('media_id')
                ->references('media_id')->on('media')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
}
