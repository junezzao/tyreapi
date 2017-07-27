<?php


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Supplier;
use App\Models\Admin\Client;

class AppendClientNameToSupplierName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $suppliers = Supplier::all();
        foreach ($suppliers as $supplier) {
            $client = Client::find($supplier->client_id);
            if (isset($client->client_name)) {
                $supplier->name = '['.$client->client_name.'] '.$supplier->name;
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $supplier->save();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $suppliers = Supplier::all();
        foreach ($suppliers as $supplier) {
            $clientName = preg_match('/(\[.*?\])/', $supplier->name, $matches);
            if (isset($matches[0])) {
                $supplier->name = trim(str_replace($matches[0], '', $supplier->name));
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $supplier->save();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
