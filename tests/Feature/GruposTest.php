<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
mutates(Grupo::class);

uses(RefreshDatabase::class);

function cp_curso(): Curso
{
    return Curso::create(['sigla' => 'IF6100', 'nombre_curso' => 'Análisis y Diseño de Sistemas', 'creditos' => 4]);
}

function cp_grupo(Curso $curso, int $numero = 1, bool $abierto = false): Grupo
{
    return Grupo::create([
        'curso_id' => $curso->id,
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => $numero,
        'abierto' => $abierto,
    ]);
}

function cp_docentes(): array
{
    return [
        Docente::create(['cod_empleado' => 1001, 'nombre_empleado' => 'Ana', 'apellidos_empleado' => 'Mora Vargas']),
        Docente::create(['cod_empleado' => 1002, 'nombre_empleado' => 'Luis', 'apellidos_empleado' => 'Rojas Solano']),
        Docente::create(['cod_empleado' => 1003, 'nombre_empleado' => 'Carla', 'apellidos_empleado' => 'Jiménez Araya']),
    ];
}

function cp_matricular_estudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
{
    for ($i = 1; $i <= $cantidad; $i++) {
        $estudiante = Estudiante::create([
            'carne' => 'C' . (30000 + $i),
            'nombre' => 'Estudiante',
            'apellidos' => 'Prueba ' . $i,
            'docente_id' => $consejero->id,
        ]);
        $grupo->estudiantes()->attach($estudiante->id);
    }
}

test('CP-01 registra un grupo con toda su información y su curso', function () {
    $grupo = cp_grupo(cp_curso(), 1, true);

    $recuperado = Grupo::find($grupo->id);

    expect($recuperado->numero_ciclo)->toBe(2)
        ->and($recuperado->ano)->toBe(2026)
        ->and($recuperado->numero_grupo)->toBe(1)
        ->and($recuperado->abierto)->toBeTrue()
        ->and($recuperado->curso->sigla)->toBe('IF6100')
        ->and($recuperado->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
    $curso = cp_curso();
    cp_grupo($curso, 1, true);
    cp_grupo($curso, 2, true);
    cp_grupo($curso, 3, false);

    $grupos = $curso->grupos;
    $abiertos = $grupos->where('abierto', true);

    expect($grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numero_grupo')->sort()->values()->all())->toBe([1, 2])
        ->and($grupos->pluck('curso_id')->unique())->toHaveCount(1);
});

test('CP-03 un grupo acepta dos docentes', function () {
    [$d1001] = cp_docentes();
    $grupo = cp_grupo(cp_curso());

    $grupo->asignarDocente(1001);
    $grupo->asignarDocente(1002);

    expect($grupo->docentes()->pluck('cod_empleado')->sort()->values()->all())->toBe([1001, 1002])
        ->and($d1001->grupos->pluck('id')->all())->toContain($grupo->id);

    $this->assertDatabaseCount('docente_grupo', 2);
});

test('CP-04 un grupo rechaza un tercer docente', function () {
    cp_docentes();
    $grupo = cp_grupo(cp_curso());
    $grupo->asignarDocente(1001);
    $grupo->asignarDocente(1002);

    expect(fn () => $grupo->asignarDocente(1003))->toThrow(DomainException::class);

    expect($grupo->docentes()->count())->toBe(2)
        ->and($grupo->docentes()->where('cod_empleado', 1003)->exists())->toBeFalse();
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    [$d1001] = cp_docentes();
    $grupo = cp_grupo(cp_curso());
    $grupo->asignarDocente(1001);
    cp_matricular_estudiantes($grupo, 14, $d1001);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())->toThrow(DomainException::class);

    expect($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    [$d1001] = cp_docentes();
    $grupo = cp_grupo(cp_curso());
    $grupo->asignarDocente(1001);
    cp_matricular_estudiantes($grupo, 15, $d1001);

    expect($grupo->puedeAbrirse())->toBeTrue();

    $grupo->abrir();

    expect($grupo->fresh()->abierto)->toBeTrue();
});