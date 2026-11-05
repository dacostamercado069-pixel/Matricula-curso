<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['curso_id', 'codigo', 'cupo', 'dia', 'hora_inicio', 'hora_fin'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class)->withTimestamps();
    }

    public function cupoLibre(): int
    {
        return $this->cupo - $this->estudiantes()->count();
    }

    public function chocaCon(Grupo $otro): bool
    {
        return $this->dia === $otro->dia
            && $this->hora_inicio < $otro->hora_fin
            && $otro->hora_inicio < $this->hora_fin;
    }
}