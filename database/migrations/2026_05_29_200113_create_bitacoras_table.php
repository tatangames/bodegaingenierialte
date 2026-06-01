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
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('nombre', 300)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ubicacion', 800)->nullable();
            $table->string('latitud', 100)->nullable();
            $table->string('longitud', 100)->nullable();
            $table->string('tiempo_utilizado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
