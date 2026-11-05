<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->string('carne')->unique();
            $table->string('nombre');
            $table->decimal('deuda', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('sigla')->unique();
            $table->string('nombre');
            $table->unsignedTinyInteger('creditos');
            $table->timestamps();
        });

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained()->cascadeOnDelete();
            $table->string('codigo');
            $table->unsignedInteger('cupo');
            $table->string('dia', 1);
            $table->string('hora_inicio', 5);
            $table->string('hora_fin', 5);
            $table->timestamps();
        });

        Schema::create('estudiante_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained()->cascadeOnDelete();
            $table->unique(['estudiante_id', 'grupo_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_grupo');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('estudiantes');
    }
};