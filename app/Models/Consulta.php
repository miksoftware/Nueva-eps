<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consulta extends Model
{
    protected $fillable = [
        'lote',
        'user_id',
        'tipo_documento',
        'numero_documento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'sexo',
        'celular',
        'telefono1',
        'telefono2',
        'correo_electronico',
        'tipo_afiliado',
        'regimen',
        'categoria',
        'ips_primaria',
        'departamento',
        'municipio',
        'estado',
        'respuesta_afiliado',
        'respuesta_paciente',
        'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
