<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveClientsForeignContraints extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->dropForeign('products_client_id_foreign');
        });

        Schema::table('purchase_batches', function ($table) {
            $table->dropForeign('purchase_batches_client_id_foreign');
            $table->dropForeign('purchase_batches_admin_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function ($table) {
            $table->foreign('client_id')->references('client_id')->on('clients');
        });

        Schema::table('purchase_batches', function ($table) {
            $table->foreign('client_id')->references('client_id')->on('clients');
            $table->foreign('admin_id')->references('admin_id')->on('client_admins');
        });
    }
}
