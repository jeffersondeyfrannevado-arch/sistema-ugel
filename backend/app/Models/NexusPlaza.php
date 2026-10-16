<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NexusPlaza extends Model
{
    use HasFactory;

    protected $table = 'nexus_plazas';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_columns' => 'array',
    ];

    public function carga()
    {
        return $this->belongsTo(NexusCarga::class, 'carga_id');
    }
}
