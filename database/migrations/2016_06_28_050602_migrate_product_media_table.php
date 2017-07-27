<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class MigrateProductMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create a snapshot of the old product_media table
        /*Schema::create('product_media_snapshot', function($table)
        {
            $table->increments('media_id');
            $table->text('media_path');
            $table->string('media_type', 100);
            $table->integer('product_id');
            $table->softDeletes();
            $table->timestamps();
            $table->string('ext', 5);
            $table->integer('sort_order');
        });*/
        Schema::dropIfExists('product_media_snapshot');
        DB::statement('CREATE TABLE product_media_snapshot LIKE product_media');
        DB::Statement("INSERT INTO product_media_snapshot SELECT * from product_media");

        //Create new product_media table
        Schema::table('product_media', function ($table) {
            $table->renameColumn('media_id', 'id');
        });

        Schema::table('product_media', function ($table) {
            $table->integer('media_id')->unsigned()->index()->after('product_id');
        });

        //Retrieve from renamed table
        $productMedias = DB::table('product_media')->get();
        foreach ($productMedias as $productMedia) {
            $tmp = explode('/', rtrim($productMedia->media_path, '/'));
            $filename = end($tmp);
            //move records from old product_media into new media table
            $mediaId = DB::table('media')->insertGetId([
                'filename'          =>  $filename,
                'ext'               =>  $productMedia->ext,
                'media_url'         =>  $productMedia->media_path,
                'media_key'         =>  $filename,
                'created_at'        =>  $productMedia->created_at,
                'updated_at'        =>  $productMedia->updated_at,
                'deleted_at'        =>  $productMedia->deleted_at,
            ]);

            DB::table('product_media')->where('id', $productMedia->id)->update(['media_id' => $mediaId]);
        }
        Schema::table('product_media', function ($table) {
            $table->dropColumn('media_path');
            $table->dropColumn('media_type');
            $table->dropColumn('ext');
        });
        Schema::table('product_media', function ($table) {
            $table->foreign('media_id')->references('media_id')->on('media')->onDelete('cascade');
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
    }
}
