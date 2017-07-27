<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Product;

class MarkBadlabAndGvcProductsAsInactive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        DB::table('products')
            ->where('client_id', '=', '2')
            ->orWhere('client_id', '=', '6')
            ->chunk(1000, function ($products) {
                foreach ($products as $product) {
                    $prod = Product::find($product->id);

                    if (!is_null($prod)) {

                        if ($prod->client_id==2)
                            $prod->name = '[Badlab] '.$prod->name;
                        else
                            $prod->name = '[GVC] '.$prod->name;

                        $prod->active = 0;
                        
                        $prod->save();
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        DB::table('products')
            ->where('client_id', '=', '2')
            ->orWhere('client_id', '=', '6')
            ->chunk(1000, function ($products) {
                foreach ($products as $product) {
                    $prod = Product::find($product->id);

                    if (!is_null($prod)) {
                        if ($prod->client_id==2)
                            $prod->name = str_replace('[Badlab] ', '', $prod->name);
                        else
                            $prod->name = str_replace('[GVC] ', '', $prod->name);

                        $prod->active = 1;
                        
                        $prod->save();
                    }
                }
            });
    }
}
