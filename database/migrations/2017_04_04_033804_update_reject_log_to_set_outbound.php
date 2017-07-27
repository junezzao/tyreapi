<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRejectLogToSetOutbound extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%stock%out%' AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%return%to%brand%' AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%reject%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%defect%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%return%merchant%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%send%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%damage%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
        DB::statement("UPDATE reject_log SET outbound = 1 WHERE remarks LIKE '%move%' AND NOT (remarks LIKE '%adjus%ment%' OR remarks LIKE '%wrong%' OR remarks LIKE '%duplicate%' OR remarks LIKE '%remove%') AND outbound = 0;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("UPDATE reject_log SET outbound = 0 WHERE outbound = 1;");
    }
}
