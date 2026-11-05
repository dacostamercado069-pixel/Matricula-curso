<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Grupo;
use DomainException;

class MatriculaService
{
    public const MAX_CREDITOS = 18;

    public function matricular(Estudiante $estudiante, Grupo $grupo): void
    {
        if ((float) $estudiante->deuda > 0) {
            throw new DomainException('El estudiante tiene deuda con la Universidad');
        }

        if ($grupo->cupoLibre() <= 0) {
            throw new DomainException('El grupo no tiene cupo');
        }

       foreach ($estudiante->grupos()->get() as $matriculado) {
            if ($grupo->chocaCon($matriculado)) {
                throw new DomainException('El horario choca con un curso ya matriculado');
            }
        }

        $creditosActuales = $estudiante->grupos()->with('curso')->get()
            ->sum(fn (Grupo $g) => $g->curso->creditos);

        if ($creditosActuales + $grupo->curso->creditos > self::MAX_CREDITOS) {
            throw new DomainException('Se excede el límite de 18 créditos');
        }

        $grupo->estudiantes()->attach($estudiante->id);
    }
}