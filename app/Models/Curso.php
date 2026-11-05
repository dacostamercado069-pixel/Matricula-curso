<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $fillable = ['sigla', 'nombre', 'creditos'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
}