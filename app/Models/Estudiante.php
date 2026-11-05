<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $fillable = ['carne', 'nombre', 'deuda'];

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class)->withTimestamps();
    }
}