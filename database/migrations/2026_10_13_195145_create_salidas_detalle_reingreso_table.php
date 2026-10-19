<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salidas_detalle_reingreso', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_salida_detalle')->unsigned();
            $table->integer('cantidad');
            $table->date('fecha');
            $table->text('justificacion');
            $table->string('archivo', 100)->nullable();
            $table->timestamps();

            $table->foreign('id_salida_detalle')->references('id')->on('salidas_detalle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas_detalle_reingreso');
    }
};
