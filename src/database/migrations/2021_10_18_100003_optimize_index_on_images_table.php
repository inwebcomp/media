<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use InWeb\Media\Images\Image;

class OptimizeIndexOnImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('images_model_object_id_filename_position_unique');
            $table->string('type', 50)->nullable()->change();
            $table->string('language', 5)->nullable()->change();

            $table->unique(['model', 'object_id', 'position', 'type', 'language'], 'main_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('main_index');
            $table->unique(['model', 'object_id', 'filename', 'position']);
        });
    }
}
