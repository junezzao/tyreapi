<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSiteColumnToChannelTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_types', function ($table) {
            $table->string('site')->after('controller')->nullable();
        });

        DB::table('channel_types')->where('name', 'Lelong')->update(['site' => 'MY']);
        DB::table('channel_types')->where('name', 'Lazada')->update(['site' => 'MY']);
        DB::table('channel_types')->where('name', 'Zalora')->update(['site' => 'MY']);
        DB::table('channel_types')->where('name', '11Street')->update(['site' => 'MY']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_types', function ($table) {
            $table->dropColumn('site');
        });
    }
}
