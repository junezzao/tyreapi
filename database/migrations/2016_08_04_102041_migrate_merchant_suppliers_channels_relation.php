<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class MigrateMerchantSuppliersChannelsRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Retrieve from renamed table
        //
            $count =0;
        $merchants = DB::table('merchants')->get();
        $client_merchant_arr = array();

        foreach ($merchants as $merchant) {
            if ($merchant->legacy_supplier_id!=null) {
                $legacy_suppliers = json_decode($merchant->legacy_supplier_id)->supplier_id;

                $suppliers = DB::table('suppliers')->whereIn('id', $legacy_suppliers);
                if (!empty($suppliers)) {
                    $suppliers->update(['merchant_id' => $merchant->id]);
                    $suppliers = $suppliers->groupBy('client_id')->get();

                        //do not link channels if merchant is deleted
                        if ($merchant->deleted_at == null) {
                            foreach ($suppliers as $supplier) {
                                $client_merchant_arr[$supplier->client_id][] = $merchant->id;
                            }
                        }
                }
            }
        }
        $max_channel_id = DB::table('channels')->max('id');
            //to get list of channel by clients

            foreach ($client_merchant_arr as $client_id => $merchants) {
                $old_channel = DB::table('channels')->where('client_id', '=', $client_id)->where('id', '<=', $max_channel_id)->get();
                foreach ($old_channel as $channel) {
                    # loop thru all channels in client
                    foreach ($merchants as $merchant_id) {
                        # add channel_merchant relation for each merchant
                        $channel_merchant = array(
                                            'channel_id'=>$channel->id,
                                            'merchant_id' => $merchant_id,
                                            'created_at'=> Carbon::now()->toDateTimeString(),
                                            'updated_at'=> Carbon::now()->toDateTimeString()
                                        );
                        DB::table('channel_merchant')->insert($channel_merchant);
                    }
                }
            }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('suppliers')->where('client_id', '<>', 0)->update(array('merchant_id'=>null));
        DB::table('channel_merchant')->truncate();
    }
}
