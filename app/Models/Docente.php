<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $fillable = ['cod_empleado', 'nombre_empleado', 'apellidos_empleado'];

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'docente_grupo');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class);
    }
}