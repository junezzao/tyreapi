<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MigrateSupplierToMerchants extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // create a column in merchants table to store old supplier_ids
        Schema::table('merchants', function (Blueprint $table) {
            //$table->string('last_name')->after('first_name');
            $table->text('legacy_supplier_id')->after('status')->nullable();
        });
        //todo: add in order by name
        $suppliers = DB::table('suppliers')->orderBy('supplier_name', 'asc')->orderBy('supplier_id', 'asc')->get();
        $prevRecord = null;
        // To prevent missing out on last row which is a combined merchant
        $addLastRow = false;
        $currentRecord;
        foreach ($suppliers as $currentRecord) {
            if (!is_null($prevRecord)) {
                if (trim($prevRecord->supplier_name) == trim($currentRecord->supplier_name)) {
                    // should be combined
                    // get the latest details and put it into the $prevRecord
                    $prevRecord->supplier_phone = $currentRecord->supplier_phone;
                    $prevRecord->supplier_mobile = $currentRecord->supplier_mobile;
                    $prevRecord->supplier_contact_person = $currentRecord->supplier_contact_person;
                    $prevRecord->supplier_email = $currentRecord->supplier_email;
                    $prevRecord->supplier_address = $currentRecord->supplier_address;
                    $prevRecord->created_at = $currentRecord->created_at;
                    $prevRecord->deleted_at = $currentRecord->deleted_at;
                    if (!is_null($currentRecord->deleted_at)) {
                        $prevRecord->legacy_delete[] = 'true';
                    } else {
                        $prevRecord->legacy_delete[] = 'false';
                    }
                    // append IDs into the legacy ID to keep track
                    $prevRecord->legacy_id[] = $currentRecord->supplier_id;
                } else {
                    // if not the same name, enter the prev record into merchants table and set new prev record
                    if (in_array('false', $prevRecord->legacy_delete)) {
                        $prevRecord->status = 'Active';
                        $prevRecord->deleted_at = null;
                    } elseif (!is_null($prevRecord->deleted_at)) {
                        // perform checking to see if the supplier has any items attached to it
                        foreach ($prevRecord->legacy_id as $suppler_id) {
                            $product = DB::table('purchase_items')
                            ->leftJoin('purchase_batches', 'purchase_batches.batch_id', '=', 'purchase_items.batch_id')
                            ->where('purchase_batches.supplier_id', '=', $suppler_id)
                            ->whereNull('purchase_batches.deleted_at')
                            ->whereNull('purchase_items.deleted_at')
                            ->count();
                            if ($product > 0) {
                                $prevRecord->status = 'Inactive';
                                $prevRecord->deleted_at = null;
                            } else {
                                $prevRecord->status = 'Inactive';
                                $prevRecord->deleted_at = Carbon::now('UTC');
                            }
                        }
                    } else {
                        $prevRecord->status = 'Active';
                        $prevRecord->deleted_at = null;
                    }
                    $legacy_id = json_encode(array('supplier_id'=>$prevRecord->legacy_id));
                    $id_length = strlen((string)$prevRecord->supplier_id);
                    DB::table('merchants')->insert([
                            'name'                  =>  trim($prevRecord->supplier_name),
                            'slug'                  =>  Str::random(6-$id_length) . $prevRecord->supplier_id,
                            'address'               =>  $prevRecord->supplier_address,
                            'contact'               =>  $prevRecord->supplier_phone,
                            'email'                 =>  $prevRecord->supplier_email,
                            'ae'                    =>  0,
                            'status'                =>  $prevRecord->status,
                            'legacy_supplier_id'    =>  $legacy_id,
                            'created_at'            =>  $prevRecord->created_at,
                            'updated_at'            =>  Carbon::now('UTC'),
                            'deleted_at'            =>  $prevRecord->deleted_at,
                        ]);
                    // set new $prevRecord
                    $prevRecord = $currentRecord;
                    // get the old supplier ID 
                    $prevRecord->legacy_id = array($currentRecord->supplier_id);
                    $prevRecord->legacy_delete = array();
                    if (!is_null($prevRecord->deleted_at)) {
                        $prevRecord->legacy_delete[] = 'true';
                    } else {
                        $prevRecord->legacy_delete[] = 'false';
                    }
                }
            } else {
                // do nothing for now and set $prevRecord for first iteration
                $prevRecord = $currentRecord;
                // get the old supplier ID 
                $prevRecord->legacy_id = array($currentRecord->supplier_id);
                $prevRecord->legacy_delete = array();
                if (!is_null($prevRecord->deleted_at)) {
                    $prevRecord->legacy_delete[] = 'true';
                } else {
                    $prevRecord->legacy_delete[] = 'false';
                }
            }
        }

        // to insert the last row
        if (in_array('false', $prevRecord->legacy_delete)) {
            $prevRecord->status = 'Active';
            $prevRecord->deleted_at = null;
        }
        if (!is_null($prevRecord->deleted_at)) {
            // perform checking to see if the supplier has any items attached to it
            foreach ($prevRecord->legacy_id as $suppler_id) {
                $product = DB::table('purchase_items')
                ->leftJoin('purchase_batches', 'purchase_batches.batch_id', '=', 'purchase_items.batch_id')
                ->where('purchase_batches.supplier_id', '=', $suppler_id)
                ->whereNull('purchase_batches.deleted_at')
                ->whereNull('purchase_items.deleted_at')
                ->count();
                if ($product > 0) {
                    $prevRecord->status = 'Inactive';
                    $prevRecord->deleted_at = null;
                } else {
                    $prevRecord->status = 'Inactive';
                    $prevRecord->deleted_at = Carbon::now('UTC');
                }
            }
        } else {
            $prevRecord->status = 'Active';
            $prevRecord->deleted_at = null;
        }
        $legacy_id = json_encode(array('supplier_id'=>$prevRecord->legacy_id));
        $id_length = strlen((string)$prevRecord->supplier_id);
        DB::table('merchants')->insert([
                'name'                  =>  trim($prevRecord->supplier_name),
                'slug'                  =>  Str::random(6-$id_length) . $prevRecord->supplier_id,
                'address'               =>  $prevRecord->supplier_address,
                'contact'               =>  $prevRecord->supplier_phone,
                'email'                 =>  $prevRecord->supplier_email,
                'status'                =>  $prevRecord->status,
                'ae'                    =>  0,
                'legacy_supplier_id'    =>  $legacy_id,
                'created_at'            =>  $prevRecord->created_at,
                'updated_at'            =>  Carbon::now('UTC'),
                'deleted_at'            =>  $prevRecord->deleted_at,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $suppliers = DB::table('merchants')->whereNotNull('legacy_supplier_id')->delete();
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('legacy_supplier_id');
        });
    }
}
