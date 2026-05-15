<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALIDAS
     */
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_tipoproyecto')->unsigned();
            $table->date('fecha');
            $table->string('descripcion', 800)->nullable();

            $table->boolean('es_transferencia')->default(false);

            $table->foreign('id_tipoproyecto')->references('id')->on('tipoproyecto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
