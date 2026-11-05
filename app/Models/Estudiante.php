<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $fillable = ['carne', 'nombre', 'apellidos', 'docente_id'];

    public function consejero()
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'estudiante_matriculados')
                    ->using(EstudianteMatriculado::class)
                    ->withPivot('nota')
                    ->withTimestamps();
    }
}