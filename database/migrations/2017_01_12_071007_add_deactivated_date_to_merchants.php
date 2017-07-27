<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Merchant;

class AddDeactivatedDateToMerchants extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('merchants', function (Blueprint $table) {
            $table->dateTime('deactivated_date')->after('legacy_supplier_id')->nullable();
        });

        Merchant::chunk(1000, function($merchants) {
            foreach($merchants as $merchant) {
                if ($merchant->status == 'Inactive')
                    $merchant->deactivated_date = '2017-01-01 00:00:00';
                else
                    $merchant->deactivated_date = null;
                $merchant->save();
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
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('deactivated_date');
        });
    }
}
