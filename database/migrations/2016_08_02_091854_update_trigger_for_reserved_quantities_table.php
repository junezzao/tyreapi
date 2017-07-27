<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTriggerForReservedQuantitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('SET GLOBAL log_bin_trust_function_creators = 1;');
        
        DB::unprepared('DROP TRIGGER IF EXISTS `sold_quantity_after_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `sold_quantity_after_update`');
        DB::unprepared("
            CREATE
                TRIGGER reserved_quantities_after_insert
                AFTER INSERT
                ON reserved_quantities FOR EACH ROW

            BEGIN
                INSERT INTO reserved_quantities_log (channel_sku_id, quantity_old, quantity_new,created_at)
                VALUES (NEW.channel_sku_id, 0, NEW.quantity,utc_timestamp);
            END;
        ");

        DB::unprepared("
            CREATE
                TRIGGER reserved_quantities_after_update
                AFTER UPDATE
                ON reserved_quantities FOR EACH ROW

            BEGIN
                INSERT INTO reserved_quantities_log (channel_sku_id, quantity_old, quantity_new,created_at)
                VALUES (NEW.channel_sku_id, OLD.quantity, NEW.quantity,utc_timestamp);
            END;
        ");

        DB::statement('SET GLOBAL log_bin_trust_function_creators = 0;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        DB::unprepared('DROP TRIGGER IF EXISTS `reserved_quantities_after_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `reserved_quantities_after_update`');
    }
}
