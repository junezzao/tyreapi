<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class CreateIssuingCompanyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('issuing_companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('address');
            $table->string('gst_reg_no');
            $table->string('prefix')->unique();
            $table->string('date_format');
            $table->string('logo_url');
            $table->string('extra')->nullable();
            $table->timestamps();
        });

        // insert initial companies
        $gvc = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Citychemo Manufacturing Sdn Bhd',
                    'address' => 'C-6-3A, Capital 3,\n2, Jalan PJU 1A/7A,\nOasis Square, Ara Damansara,\n47301 Petaling Jaya,\nSelangor Darul Ehsan, Malaysia.',
                    'gst_reg_no' => '000858501120',
                    'prefix' => 'GVC',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/gvc-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $bl = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Citychemo Manufacturing Sdn Bhd',
                    'address' => 'C-6-3A, Capital 3,\n2, Jalan PJU 1A/7A,\nOasis Square, Ara Damansara,\n47301 Petaling Jaya,\nSelangor Darul Ehsan, Malaysia.',
                    'gst_reg_no' => '000858501120',
                    'prefix' => 'BL',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/badlab-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $mel = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Omniscient Sdn Bhd',
                    'address' => 'Lot G341, Ground Floor, New Wing,\n 1 utama Shopping Centre,\n 1 Lebuh Bandar Utama,\n 47800 Petaling Jaya,\n Selangor, Malaysia.',
                    'gst_reg_no' => '001875091456',
                    'prefix' => 'MEL',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/melissa-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $plh = DB::table('issuing_companies')->insertGetId([
                    'name' => 'BRG Polo Haus Sdn Bhd',
                    'address' => 'Lot 1897B, Jalan KPB 9\nKawasan Perindustrian Kg. Baru Balakong\nSeri Kembangan\n43300 Selangor\nMalaysia',
                    'gst_reg_no' => '001434722304',
                    'prefix' => 'OSHB',
                    'date_format' => 'my',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/plh-logo.png',
                    'extra' => '{"Fax No": "603-896 44 991", "Email": "os@polohaus.com"}',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $fmhw = DB::table('issuing_companies')->insertGetId([
                    'name' => 'FM Hubwire Sdn Bhd',
                    'address' => 'Unit 17-7, Level 7, Block C1,\nDataran Prima, Jalan PJU 1/41,\n47301 Petaling Jaya, Malaysia',
                    'gst_reg_no' => '001584476160',
                    'prefix' => 'HC',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/fmhw-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        /*
        Removed old company - old invoice should never be re-generated again and prefix need to be unique
        $hw = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Hubwire Sdn Bhd',
                    'address' => 'Unit 17-7, Level 7, Block C1,\nDataran Prima, Jalan PJU 1/41,\n47301 Petaling Jaya, Malaysia',
                    'gst_reg_no' => '001817251840',
                    'prefix' => 'HC',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/hubwire-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);
        */
        $fs = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Fabspy Sdn Bhd',
                    'address' => 'T008 Level 3, Mid Valley Megamall,\nLingkaran Syed Putra, Mid Valley City,\nKuala Lumpur, 59200 Malaysia.',
                    'gst_reg_no' => '002033442816',
                    'prefix' => 'FS',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/fabspy-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $fsmv = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Fabspy Sdn Bhd',
                    'address' => 'T008 Level 3, Mid Valley Megamall,\nLingkaran Syed Putra, Mid Valley City,\nKuala Lumpur, 59200 Malaysia.',
                    'gst_reg_no' => '002033442816',
                    'prefix' => 'FSMV',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/fabspy-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        $bata = DB::table('issuing_companies')->insertGetId([
                    'name' => 'Bata Primavera Sdn Bhd',
                    'address' => 'B-17, Menara Bata,\nNo. 8 Jalan PJU 8/8A,\nDamansara Perdana,\n47820 Petaling Jaya,\nSelangor, Malaysia.',
                    'gst_reg_no' => '001887125504',
                    'prefix' => 'BATA',
                    'date_format' => 'ymd',
                    'logo_url' => 'https://s3-ap-southeast-1.amazonaws.com/cdn.hubwire.com/tax-invoice-logo/bata-logo.png',
                    'extra' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
               ]);

        if(!Schema::hasColumn('channels', 'issuing_company')) {
            Schema::table('channels', function($table) {
                $table->tinyInteger('issuing_company')->after('hidden');
            });
        }

        $company = [4 => $bl, 33 => $bl, 12 => $gvc, 8 => $fs, 36 => $gvc, 51 => $mel, 52 => $mel, 49 => $bata, 54 => $bata, 13 => $fsmv];

        $channels = DB::table('channels')->get();
        foreach ($channels as $channel) {
            DB::table('channels')->where('id', $channel->id)->update(['issuing_company' => (isset($company[$channel->id]) ? $company[$channel->id] : $fmhw)]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('issuing_companies');
        Schema::table('channels', function($table) {
            $table->dropColumn('issuing_company');
        });
    }
}
