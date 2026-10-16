<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NexusCarga extends Model
{
    use HasFactory;

    protected $table = 'nexus_cargas';

    protected $fillable = [
        'nombre_archivo',
        'archivo_path',
        'hash',
        'total_filas',
        'total_colegios',
        'nombrados',
        'contratados',
        'vacantes',
        'designados',
        'estado',
        'error_msg',
    ];

    public function plazas()
    {
        return $this->hasMany(NexusPlaza::class, 'carga_id')->orderBy('orden');
    }
}
