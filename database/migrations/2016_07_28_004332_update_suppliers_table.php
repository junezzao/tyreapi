<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_batches', function ($table) {
            $table->dropForeign('purchase_batches_supplier_id_foreign');
        });
        Schema::table('suppliers', function ($table) {
            $table->dropForeign('suppliers_client_id_foreign');
            $table->renameColumn('supplier_id', 'id');
            $table->renameColumn('supplier_name', 'name');
            $table->renameColumn('supplier_phone', 'phone');
            $table->renameColumn('supplier_mobile', 'mobile');
            $table->renameColumn('supplier_contact_person', 'contact_person');
            $table->renameColumn('supplier_email', 'email');
            $table->renameColumn('supplier_address', 'address');
            $table->integer('merchant_id')->unsigned()->nullable()->after('supplier_address');
        });
        Schema::table('purchase_batches', function ($table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_batches', function ($table) {
            $table->dropForeign('purchase_batches_supplier_id_foreign');
        });
        
        Schema::table('suppliers', function ($table) {
            $table->dropColumn('merchant_id');
            $table->renameColumn('id', 'supplier_id');
            $table->renameColumn('name', 'supplier_name');
            $table->renameColumn('phone', 'supplier_phone');
            $table->renameColumn('mobile', 'supplier_mobile');
            $table->renameColumn('contact_person', 'supplier_contact_person');
            $table->renameColumn('email', 'supplier_email');
            $table->renameColumn('address', 'supplier_address');
            $table->foreign('client_id')->references('client_id')->on('clients');
            
        });
        Schema::table('purchase_batches', function ($table) {
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers');
        });
    }
}
