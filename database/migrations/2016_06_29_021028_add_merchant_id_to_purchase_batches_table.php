<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMerchantIdToPurchaseBatchesTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('purchase_batches', function (Blueprint $table) {
			$table->integer('merchant_id')->after('supplier_id');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('purchase_batches', function (Blueprint $table) {
			$table->dropColumn('merchant_id');
		});
	}
}
