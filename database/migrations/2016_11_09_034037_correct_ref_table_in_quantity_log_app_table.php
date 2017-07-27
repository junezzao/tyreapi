<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\QuantityLogApp;

class CorrectRefTableInQuantityLogAppTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        QuantityLogApp::where('ref_table', '=', 'Sales')->update(['ref_table' => 'Order']);
        QuantityLogApp::where('ref_table', '=', 'RefundLog')->update(['ref_table' => 'ReturnLog']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // not revertable
    }
}
