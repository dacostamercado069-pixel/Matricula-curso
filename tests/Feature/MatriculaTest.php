<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;
mutates(Grupo::class);

// Helpers con prefijo mt_ para no chocar con los cp_ de GruposTest.php.
// Cada test arma sus propios datos: ninguno depende de otro.


function mt_grupo01(): Grupo
{
    $curso = Curso::create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);

    return Grupo::create([
        'curso_id' => $curso->id,
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => 1,
        'abierto' => false,
    ]);
}

function mt_docente(int $cod, string $nombre, string $apellidos): Docente
{
    return Docente::create([
        'cod_empleado' => $cod,
        'nombre_empleado' => $nombre,
        'apellidos_empleado' => $apellidos,
    ]);
}

function mt_docente1001(): Docente
{
    return mt_docente(1001, 'Ana', 'Mora Vargas');
}

function mt_docente1002(): Docente
{
    return mt_docente(1002, 'Luis', 'Rojas Solano');
}

function mt_estudianteC12345(Docente $consejero): Estudiante
{
    return Estudiante::create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'docente_id' => $consejero->id,
    ]);
}

function mt_nota_de(Grupo $grupo, Estudiante $estudiante): ?EstudianteMatriculado
{
    return EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();
}

test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    $grupo = mt_grupo01();

    for ($i = 1; $i <= 20; $i++) {
        $estudiante = Estudiante::create([
            'carne' => 'C' . (40000 + $i),
            'nombre' => 'Estudiante',
            'apellidos' => 'Prueba ' . $i,
        ]);
        $grupo->estudiantes()->attach($estudiante->id);
    }

    expect($grupo->estudiantes()->count())->toBe(20)
        ->and($grupo->docentes()->count())->toBe(0);

    expect($grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);

    // Poscondición
    expect($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    $docente = mt_docente1001();
    expect($docente->estudiantes)->toHaveCount(0);

    $estudiante = mt_estudianteC12345($docente);

    expect($estudiante->consejero->cod_empleado)->toEqual(1001);

    $docente = $docente->fresh();
    expect($docente->estudiantes)->toHaveCount(1)
        ->and($docente->estudiantes->first()->carne)->toBe('C12345');

    // Poscondición: la llave foránea apunta al docente 1001
    expect($estudiante->fresh()->docente_id)->toBe($docente->id);
});

test('CP-09 cambiar de consejero reemplaza al anterior, no lo suma', function () {
    $docente1001 = mt_docente1001();
    $docente1002 = mt_docente1002();
    $estudiante = mt_estudianteC12345($docente1001);

    $estudiante->consejero()->associate($docente1002);
    $estudiante->save();

    $docente1001 = $docente1001->fresh();
    $docente1002 = $docente1002->fresh();

    expect($estudiante->fresh()->consejero->cod_empleado)->toEqual(1002)
        ->and($docente1001->estudiantes)->toHaveCount(0)
        ->and($docente1002->estudiantes)->toHaveCount(1);

    // Poscondición: un único consejero (1002) y un único registro del estudiante
    expect($estudiante->fresh()->docente_id)->toBe($docente1002->id);
    expect(Estudiante::where('carne', 'C12345')->count())->toBe(1);
});

test('CP-10 al matricular, la nota queda vacía', function () {
    $grupo = mt_grupo01();
    $estudiante = mt_estudianteC12345(mt_docente1001());

    $grupo->estudiantes()->attach($estudiante->id);

    $pivote = $grupo->estudiantes->first()->pivot;

    expect($pivote)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivote->nota)->toBeNull();

    // Poscondición: 1 registro con grupo_id y estudiante_id correctos
    $this->assertDatabaseCount('estudiante_matriculados', 1);
    $this->assertDatabaseHas('estudiante_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = mt_grupo01();
    $docente = mt_docente1001();
    $estudiante = mt_estudianteC12345($docente);
    $otro = Estudiante::create([
        'carne' => 'C50001',
        'nombre' => 'Otro',
        'apellidos' => 'Estudiante',
        'docente_id' => $docente->id,
    ]);
    $grupo->estudiantes()->attach([$estudiante->id, $otro->id]);

    $grupo->registrarNota($estudiante, 8.5);

    $matricula = mt_nota_de($grupo, $estudiante);

    expect($matricula->nota)->toBeFloat()->toBe(8.5);

    // Poscondición: solo cambió la nota de C12345; el resto queda igual
    expect((int) $matricula->grupo_id)->toBe($grupo->id)
        ->and((int) $matricula->estudiante_id)->toBe($estudiante->id);
    expect(mt_nota_de($grupo, $otro)->nota)->toBeNull();
    $this->assertDatabaseCount('estudiante_matriculados', 2);
});

test('CP-12 rechaza notas menores que 0 o mayores que 10', function (float|int $nota) {
    $grupo = mt_grupo01();
    $estudiante = mt_estudianteC12345(mt_docente1001());
    $grupo->estudiantes()->attach($estudiante->id);

    expect(fn () => $grupo->registrarNota($estudiante, $nota))
        ->toThrow(InvalidArgumentException::class);

    // Poscondición: la nota sigue en null
    expect(mt_nota_de($grupo, $estudiante)->nota)->toBeNull();
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = mt_grupo01();
    $estudiante = mt_estudianteC12345(mt_docente1001());
    $grupo->estudiantes()->attach($estudiante->id);

    expect(fn () => $grupo->estudiantes()->attach($estudiante->id))
        ->toThrow(QueryException::class);

    // Poscondición: un solo registro de C12345 en el grupo 01
    expect(
        EstudianteMatriculado::where('grupo_id', $grupo->id)
            ->where('estudiante_id', $estudiante->id)
            ->count()
    )->toBe(1);
});
test('C3 acepta las notas límite 0 y 10', function (int $nota) {
    $grupo = mt_grupo01();
    $estudiante = mt_estudianteC12345(mt_docente1001());
    $grupo->estudiantes()->attach($estudiante->id);

    $grupo->registrarNota($estudiante, $nota);

    expect(mt_nota_de($grupo, $estudiante)->nota)
        ->toBeFloat()
        ->toBe((float) $nota);
})->with([0, 10]);