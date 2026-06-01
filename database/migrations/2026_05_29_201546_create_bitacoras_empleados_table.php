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
        Schema::create('bitacoras_empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_bitacora')->constrained('bitacoras')->onDelete('cascade');
            $table->foreignId('id_empleado')->constrained('empleados')->onDelete('cascade');
            $table->timestamps();

            // Prevenir duplicados
            $table->unique(['id_bitacora', 'id_empleado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras_empleados');
    }
};
