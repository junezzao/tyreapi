<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRemarksType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report_remarks', function(Blueprint $table){
            $table->string('type')->after('added_by');
            $table->boolean('resolve_status')->default(0)->after('type');
            $table->dateTime('completed_at')->nullable()->after('updated_at');
        });

        $tprRemarks = DB::table('third_party_report_remarks')->get();

        foreach($tprRemarks as $tprRemark){
            if($tprRemark->added_by == 0){
                $type = 'error';
            }else{
                $type = 'general';
            }
            DB::table('third_party_report_remarks')->where('id', $tprRemark->id)->update(['type' => $type]);
        }


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_report_remarks', function(Blueprint $table){
            $table->dropColumn('type');
            $table->dropColumn('resolve_status');
            $table->dropColumn('completed_at');
        });
    }
}
