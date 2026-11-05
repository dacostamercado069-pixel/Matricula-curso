<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('sigla')->unique();
            $table->string('nombre_curso');
            $table->unsignedTinyInteger('creditos');
            $table->timestamps();
        });

        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cod_empleado')->unique();
            $table->string('nombre_empleado');
            $table->string('apellidos_empleado');
            $table->timestamps();
        });

        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->string('carne')->unique();
            $table->string('nombre');
            $table->string('apellidos');
            $table->foreignId('docente_id')->nullable()->constrained('docentes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_ciclo');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('numero_grupo');
            $table->boolean('abierto')->default(false);
            $table->timestamps();
        });

        Schema::create('docente_grupo', function (Blueprint $table) {
            $table->foreignId('docente_id')->constrained('docentes')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->primary(['docente_id', 'grupo_id']);
        });

        Schema::create('estudiante_matriculados', function (Blueprint $table) {
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->decimal('nota', 4, 2)->nullable();
            $table->timestamps();
            $table->primary(['grupo_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_matriculados');
        Schema::dropIfExists('docente_grupo');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('estudiantes');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('cursos');
    }
};