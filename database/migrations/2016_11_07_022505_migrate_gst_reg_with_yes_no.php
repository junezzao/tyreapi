<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateGstRegWithYesNo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('issuing_companies')->chunk(1000, function($issuing_companies){
            foreach($issuing_companies as $company){
                if(!empty($company->gst_reg_no)){
                    DB::table('issuing_companies')->where('id', $company->id)->update(['gst_reg' => 1]);
                }else if(empty($company->gst_reg_no)){
                    DB::table('issuing_companies')->where('id', $company->id)->update(['gst_reg' => 0]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('issuing_companies')->chunk(1000, function($issuing_companies){
            foreach($issuing_companies as $company){
                DB::table('issuing_companies')->update(['gst_reg' => 0]);
            }
        });
    }
    
              
}
