<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('members', function ($table) {
            $table->renameColumn('member_id', 'id');
            $table->renameColumn('client_id', 'merchant_id');

            //$table->dropForeign('members_client_id_foreign');
            //$table->dropIndex('members_client_id_foreign');
            //$table->foreign('merchant_id')->references('id')->on('merchants');

            DB::statement('ALTER TABLE members MODIFY COLUMN deleted_at TIMESTAMP NULL AFTER member_birthday');
            DB::statement('ALTER TABLE members MODIFY COLUMN created_at TIMESTAMP NULL AFTER deleted_at');
            DB::statement('ALTER TABLE members MODIFY COLUMN updated_at TIMESTAMP NULL AFTER created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('members', function ($table) {
            DB::statement('ALTER TABLE members MODIFY COLUMN deleted_at TIMESTAMP NULL AFTER merchant_id');
            DB::statement('ALTER TABLE members MODIFY COLUMN created_at TIMESTAMP NULL AFTER deleted_at');
            DB::statement('ALTER TABLE members MODIFY COLUMN updated_at TIMESTAMP NULL AFTER created_at');

            $table->renameColumn('id', 'member_id');
            $table->renameColumn('merchant_id', 'client_id');

            //$table->dropForeign('members_merchant_id_foreign');
            //$table->dropIndex('members_merchant_id_foreign');
            //$table->foreign('client_id')->references('client_id')->on('clients');
        });
    }
}
