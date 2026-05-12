<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTipoproyectoTable extends Migration
{
    /**
     * LISTADO DE PROYECTOS
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipoproyecto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 800);

            // 0: NO TRANSFERIDO, 1: SI TRANSFERIDO
            $table->boolean('transferido');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipoproyecto');
    }
}
