<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyPostcodeColumnsToNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_postcode', 10)->nullable()->change();
        });

        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->string('address_postal_code', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_postcode', 10)->nullable(false)->change();
        });

        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->string('address_postal_code', 255)->nullable(false)->change();
        });
    }
}
