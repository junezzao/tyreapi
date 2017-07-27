<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddModifiedByToThirdPartyReportLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report_log', function (Blueprint $table) {
            $table->integer('modified_by')->unsigned()->after('field');
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('third_party_report_log', 'modified_by'))
        {
            Schema::table('third_party_report_log', function (Blueprint $table) {
                $table->dropForeign('third_party_report_log_modified_by_foreign');
                $table->dropColumn('modified_by');
            });
        }
    }
}
