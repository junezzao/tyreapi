<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use App\Models\Admin\Merchant;
use Bican\Roles\Models\Role;
use Carbon\Carbon;

class InsertAeToMerchantTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $aeUsers = User::whereIn('email', ['likhang@hubwire.com', 'licco@hubwire.com', 'xuan@hubwire.com'])->get();
        $aeRole = Role::where('name', 'Account Executive')->first();
        foreach ($aeUsers as $user) {
            //detach all old roles
            $user->detachAllRoles();

            //update the category
            $user->category = 'Account Executive';
            $user->save();

            // attach new AE role
            $user->attachRole($aeRole);
        }

        // categorise the merchants to AE
        $this->assignMerchants();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    private function assignMerchants()
    {
        Excel::load(storage_path('app').'/merchant_list_updated.csv', function ($reader) {
            $aeLists = User::select('id', 'first_name')->where('category', 'Account Executive')->get();

            $list = array();
            foreach ($aeLists as $l) {
                $list[trim(strtolower($l->first_name))] = $l->id;
            }

            $merchants = $reader->get();
            foreach ($merchants as $merchant) {
                $m = Merchant::withTrashed()->find(intval($merchant->id));
                if (!empty($m)) {
                    // set default for contact field
                    if (empty($m->contact)) {
                        $m->contact = '1111111111';
                    }

                    //update slug
                    if ($merchant->slug != null) {
                        $m->slug = $merchant->slug;
                    }

                    //update AE
                    if($merchant->ae != null)
                        $m->ae = $list[trim(strtolower($merchant->ae))];

                    // update status    
                    if (trim($merchant->status) == 'Deleted' && trim($merchant->ae) == 0) {
                        if ($m->status != $merchant->status && $m->status != 'Deleted') {
                            $m->status = 'Deleted';
                            $m->deleted_at = Carbon::now();
                        }
                    }else{
                        $m->status = $merchant->status;
                    }
                
                    $m->save();
                }
            }
        });
    }
}
