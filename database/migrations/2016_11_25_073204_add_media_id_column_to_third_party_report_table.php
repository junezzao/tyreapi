<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMediaIdColumnToThirdPartyReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report', function (Blueprint $table) {
            $table->integer('media_id')->unsigned()->after('id');
            $table->foreign('media_id')->references('media_id')->on('media')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('third_party_report', 'media_id'))
        {
            Schema::table('third_party_report', function (Blueprint $table) {
                $table->dropForeign('third_party_report_media_id_foreign');
                $table->dropColumn('media_id');
            });
        }
    }
}
