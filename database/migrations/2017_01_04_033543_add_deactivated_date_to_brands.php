<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Brand;

class AddDeactivatedDateToBrands extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('brands', function (Blueprint $table) {
            $table->dateTime('deactivated_date')->after('active')->nullable();
        });

        Brand::chunk(1000, function($brands) {
            foreach($brands as $brand) {
                if (!$brand->active)
                    $brand->deactivated_date = '2017-01-01 00:00:00';
                else
                    $brand->deactivated_date = null;
                $brand->save();
            }
        });    

        // set the status of all deleted brands as inactive
        Brand::onlyTrashed()->chunk(1000, function($brands) {
            foreach($brands as $brand) {
                if ($brand->active)
                    $brand->active = 0;
                $brand->save();
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
        //
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('deactivated_date');
        });
    }
}
