<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddActiveAndArrangementProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            DB::statement("ALTER TABLE products MODIFY COLUMN deleted_at timestamp NULL AFTER brand_id");
            DB::statement("ALTER TABLE products MODIFY COLUMN updated_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER brand_id");
            DB::statement("ALTER TABLE products MODIFY COLUMN created_at timestamp DEFAULT '0000-00-00 00:00:00' AFTER brand_id");
            $table->integer('merchant_id')->after('client_id')->nullable();
            $table->tinyInteger('active')->default(1)->after('client_id');
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
            $table->dropColumn(['active', 'merchant_id']);
        });
    }
}
