<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\JsonResponse;

class ConsultaCedulaController extends Controller
{
    /**
     * Retorna la información más reciente de un afiliado por cédula.
     *
     * GET /api/consulta/cedula/{cedula}
     */
    public function show(string $cedula): JsonResponse
    {
        $resultado = Consulta::where('numero_documento', $cedula)
            ->where('estado', 'completado')
            ->latest('updated_at')
            ->first();

        if (! $resultado) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron resultados para la cédula proporcionada.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Consulta exitosa.',
            'data'    => [
                'cedula'              => $resultado->numero_documento,
                'tipo_documento'      => $resultado->tipo_documento,
                'primer_nombre'       => $resultado->primer_nombre,
                'segundo_nombre'      => $resultado->segundo_nombre,
                'primer_apellido'     => $resultado->primer_apellido,
                'segundo_apellido'    => $resultado->segundo_apellido,
                'sexo'                => $resultado->sexo,
                'celular'             => $resultado->celular,
                'telefono1'           => $resultado->telefono1,
                'telefono2'           => $resultado->telefono2,
                'correo_electronico'  => $resultado->correo_electronico,
                'tipo_afiliado'       => $resultado->tipo_afiliado,
                'regimen'             => $resultado->regimen,
                'categoria'           => $resultado->categoria,
                'ips_primaria'        => $resultado->ips_primaria,
                'departamento'        => $resultado->departamento,
                'municipio'           => $resultado->municipio,
                'consultado_en'       => $resultado->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
