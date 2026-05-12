<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DETALLE DEL TRASPASO DE MATERIALES
     */
    public function up(): void
    {
        Schema::create('transferencia_detalle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_transferencia')->unsigned();
            $table->bigInteger('id_entrada_detalle')->unsigned();
            $table->integer('cantidad_transferencia');
            $table->bigInteger('id_tipoproyecto_destino')->unsigned()->nullable();

            $table->foreign('id_tipoproyecto_destino')->references('id')->on('tipoproyecto');
            $table->foreign('id_transferencia')->references('id')->on('transferencia');
            $table->foreign('id_entrada_detalle')->references('id')->on('entradas_detalle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencia_detalle');
    }
};
