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
        Schema::create('bitacoras_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_bitacora')->constrained('bitacoras')->onDelete('cascade');
            $table->string('imagen'); // Ruta o nombre del archivo
            $table->text('descripcion')->nullable();
            $table->timestamps();

            // Índice para búsquedas rápidas
            $table->index('id_bitacora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras_fotos');
    }
};
