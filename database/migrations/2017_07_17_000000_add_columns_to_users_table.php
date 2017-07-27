<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('operation_type')->nullable()->after('contact_no');
            $table->string('company_name')->nullable()->after('operation_type');
            $table->string('address_line_1')->nullable()->after('company_name');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('address_city')->nullable()->after('address_line_2');
            $table->string('address_postcode')->nullable()->after('address_city');
            $table->string('address_state')->nullable()->after('address_postcode');
            $table->string('address_country')->nullable()->after('address_state');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('operation_type');
            $table->dropColumn('company_name');
            $table->dropColumn('address_line_1');
            $table->dropColumn('address_line_2');
            $table->dropColumn('address_city');
            $table->dropColumn('address_postcode');
            $table->dropColumn('address_state');
            $table->dropColumn('address_country');
        });
    }
}
