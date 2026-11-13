<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Grupo extends Model
{
    protected $fillable = ['numero_ciclo', 'ano', 'numero_grupo', 'abierto', 'curso_id'];

    protected $casts = ['abierto' => 'boolean'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function docentes()
    {
        return $this->belongsToMany(Docente::class, 'docente_grupo');
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_matriculados')
                    ->using(EstudianteMatriculado::class)
                    ->withPivot('nota')
                    ->withTimestamps();
    }

    public function asignarDocente(int $codEmpleado): void
    {
        $docente = Docente::where('cod_empleado', $codEmpleado)->firstOrFail();

        if ($this->docentes()->count() >= 2) {
            throw new DomainException('Un grupo no puede tener más de 2 docentes.');
        }

        $this->docentes()->syncWithoutDetaching([$docente->id]);
    }

    public function puedeAbrirse(): bool
    {
        return $this->estudiantes()->count() >= 15
            && $this->docentes()->count() >= 1;
    }

    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException('El grupo no cumple las condiciones para abrirse.');
        }

        $this->update(['abierto' => true]);
    }

    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < 0 || $nota > 10) {
            throw new InvalidArgumentException('La nota debe estar entre 0 y 10.');
        }

        $this->estudiantes()->updateExistingPivot($estudiante->id, ['nota' => $nota]);
    }
}