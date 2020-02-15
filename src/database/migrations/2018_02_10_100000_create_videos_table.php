<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \InWeb\Media\Videos\Video;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model');
            $table->string('object_id');
            $table->string('filename')->nullable();
            $table->string('url')->nullable();
            Video::positionColumn($table);
            Video::mainColumn($table);
            $table->timestamps();

            $table->unique(array('model', 'object_id', 'filename'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('videos');
    }
}
