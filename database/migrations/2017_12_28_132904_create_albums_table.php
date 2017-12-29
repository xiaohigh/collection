
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlbumsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->increments('id');
            $table->string('albumID');
            $table->string('albumMID');
            $table->string('albumName');
            $table->string('albumtype')->nullable();
            $table->string('company')->nullable();
            $table->string('desc')->nullable();
            $table->string('lan')->nullable();
            $table->integer('listen_count')->nullable();
            $table->string('pubTime')->nullable();
            
            $table->string('singer_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('albums');
    }
}
