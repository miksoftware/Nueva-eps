<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\JsonResponse;

class ConsultaCedulaController extends Controller
{
    /**
     * Retorna el historial completo de consultas de un afiliado por cédula,
     * ordenado del más reciente al más antiguo.
     *
     * GET /api/consulta/cedula/{cedula}
     */
    public function show(string $cedula): JsonResponse
    {
        $resultados = Consulta::where('numero_documento', $cedula)
            ->where('estado', 'completado')
            ->latest('updated_at')
            ->get();

        if ($resultados->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron resultados para la cédula proporcionada.',
                'data'    => null,
            ], 404);
        }

        $data = $resultados->map(fn (Consulta $r) => [
            'cedula'              => $r->numero_documento,
            'tipo_documento'      => $r->tipo_documento,
            'primer_nombre'       => $r->primer_nombre,
            'segundo_nombre'      => $r->segundo_nombre,
            'primer_apellido'     => $r->primer_apellido,
            'segundo_apellido'    => $r->segundo_apellido,
            'sexo'                => $r->sexo,
            'celular'             => $r->celular,
            'telefono1'           => $r->telefono1,
            'telefono2'           => $r->telefono2,
            'correo_electronico'  => $r->correo_electronico,
            'tipo_afiliado'       => $r->tipo_afiliado,
            'regimen'             => $r->regimen,
            'categoria'           => $r->categoria,
            'ips_primaria'        => $r->ips_primaria,
            'departamento'        => $r->departamento,
            'municipio'           => $r->municipio,
            'consultado_en'       => $r->updated_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consulta exitosa.',
            'total'   => $data->count(),
            'data'    => $data,
        ]);
    }
}
