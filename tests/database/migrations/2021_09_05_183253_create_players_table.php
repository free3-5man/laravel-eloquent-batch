<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->date('birthday');
            $table->double('salary_per_year');    // the unit is M
            $table->boolean('is_captain')->default(false);
            $table->integer('apps');    // played matches number
            $table->dateTime('last_goal_at')->nullable();
            $table->jsonb('attributes');
            $table->jsonb('positions');
            $table->string('complex_string')->nullable();
            $table->jsonb('complex_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('players');
    }
}
