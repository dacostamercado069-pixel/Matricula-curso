<?php

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearGrupo(Curso $curso, string $codigo, int $cupo, string $dia, string $inicio, string $fin): Grupo
{
    return Grupo::create([
        'curso_id' => $curso->id,
        'codigo' => $codigo,
        'cupo' => $cupo,
        'dia' => $dia,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ]);
}

function crearEstudiante(int $creditosPrevios = 0, float $deuda = 0): Estudiante
{
    $estudiante = Estudiante::create([
        'carne' => 'C20050',
        'nombre' => 'Estudiante de prueba',
        'deuda' => $deuda,
    ]);

    if ($creditosPrevios > 0) {
        $previo = Curso::create(['sigla' => 'PREV', 'nombre' => 'Cursos previos', 'creditos' => $creditosPrevios]);
        $grupoPrevio = crearGrupo($previo, 'G01', 5, 'K', '13:00', '16:00');
        $grupoPrevio->estudiantes()->attach($estudiante->id);
    }

    return $estudiante;
}

function cursoIF0004(int $creditos = 4): Curso
{
    return Curso::create(['sigla' => 'IF-0004', 'nombre' => 'Programación', 'creditos' => $creditos]);
}

test('CP1 matrícula exitosa', function () {
    $estudiante = crearEstudiante(10);
    $g01 = crearGrupo(cursoIF0004(), 'G01', 5, 'L', '08:00', '11:00');

    app(MatriculaService::class)->matricular($estudiante, $g01);

    $this->assertDatabaseHas('estudiante_grupo', [
        'estudiante_id' => $estudiante->id,
        'grupo_id' => $g01->id,
    ]);
});

test('CP2 rechaza al estudiante con deuda', function () {
    $estudiante = crearEstudiante(0, 15000);
    $g01 = crearGrupo(cursoIF0004(), 'G01', 5, 'L', '08:00', '11:00');

    expect(fn () => app(MatriculaService::class)->matricular($estudiante, $g01))
        ->toThrow(DomainException::class, 'deuda');

    $this->assertDatabaseCount('estudiante_grupo', 0);
});

test('CP3 rechaza un grupo sin cupo', function () {
    $estudiante = crearEstudiante(10);
    $g02 = crearGrupo(cursoIF0004(), 'G02', 0, 'L', '08:00', '11:00');

    expect(fn () => app(MatriculaService::class)->matricular($estudiante, $g02))
        ->toThrow(DomainException::class, 'cupo');
});

test('CP4 rechaza un choque de horario', function () {
    $estudiante = crearEstudiante(10);
    $curso = cursoIF0004();
    $g01 = crearGrupo($curso, 'G01', 5, 'L', '08:00', '11:00');
    $g03 = crearGrupo($curso, 'G03', 5, 'L', '09:00', '12:00');

    $servicio = app(MatriculaService::class);
    $servicio->matricular($estudiante, $g01);

    expect(fn () => $servicio->matricular($estudiante, $g03))
        ->toThrow(DomainException::class, 'choca');
});

test('CP5a acepta exactamente 18 créditos (límite)', function () {
    $estudiante = crearEstudiante(14);
    $g01 = crearGrupo(cursoIF0004(4), 'G01', 5, 'L', '08:00', '11:00');

    app(MatriculaService::class)->matricular($estudiante, $g01);

    $this->assertDatabaseHas('estudiante_grupo', ['grupo_id' => $g01->id]);
});

test('CP5b rechaza 19 créditos (excede el límite)', function () {
    $estudiante = crearEstudiante(14);
    $g01 = crearGrupo(cursoIF0004(5), 'G01', 5, 'L', '08:00', '11:00');

    expect(fn () => app(MatriculaService::class)->matricular($estudiante, $g01))
        ->toThrow(DomainException::class, '18 créditos');
});

test('CP5c acepta 4 créditos sin créditos previos', function () {
    $estudiante = crearEstudiante(0);
    $g01 = crearGrupo(cursoIF0004(4), 'G01', 5, 'L', '08:00', '11:00');

    app(MatriculaService::class)->matricular($estudiante, $g01);

    $this->assertDatabaseHas('estudiante_grupo', ['grupo_id' => $g01->id]);
});

test('CP6 rechaza un grupo sin cupo y luego matricula otro con cupo', function () {
    $estudiante = crearEstudiante(10);
    $curso = cursoIF0004();
    $g02 = crearGrupo($curso, 'G02', 0, 'K', '08:00', '11:00');
    $g01 = crearGrupo($curso, 'G01', 5, 'L', '08:00', '11:00');

    $servicio = app(MatriculaService::class);

    expect(fn () => $servicio->matricular($estudiante, $g02))
        ->toThrow(DomainException::class, 'cupo');

    $servicio->matricular($estudiante, $g01);

    $this->assertDatabaseHas('estudiante_grupo', ['grupo_id' => $g01->id]);
});