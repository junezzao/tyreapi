<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
     * Product Tags
     *  Things to consider should there be a need to convert this to Tag relation . Currently not used for reasons below:
     *     1. Need extra computation when it involve handling products from different merchants with the same tags on AE view
     *       2. Nobody is going to maintain the tags which are unused (create/delete)
     *       3. Extra Joins needed.
     *
     *       Assumptions
     *       1. Tag should never be editable only assign and unassign by single or by bulk
     *
     * @author Yuki AY <yuki@hubwire.com>
     * @version 3.0
     */
class CreateProductTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->string('value', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->index('value');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('product_tags');
    }
}
