<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EpsScraperService
{
    protected string $baseUrl;
    protected ?string $token = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.eps.api_url', 'https://api.referencias.nuevaeps.com.co'), '/');
    }

    protected function http()
    {
        return Http::withOptions([
            'verify' => false,
            'curl' => [
                CURLOPT_RESOLVE => [
                    'api.referencias.nuevaeps.com.co:443:104.18.27.237',
                ],
            ],
        ])
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'es-CO,es;q=0.9,en;q=0.8',
            'Origin' => 'https://pwa.referencias.nuevaeps.com.co',
            'Referer' => 'https://pwa.referencias.nuevaeps.com.co/',
        ])
        ->timeout(30);
    }

    public function consultarSedes(string $nit): array
    {
        $url = "{$this->baseUrl}/ConsultarSedesPorNITPrestador";

        Log::info('[EPS] Consultando sedes', [
            'url' => $url,
            'nit_prestador' => $nit,
        ]);

        try {
            $response = $this->http()->get($url, ['nit_prestador' => $nit]);

            Log::info('[EPS] Respuesta sedes', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('[EPS] Excepción consultando sedes', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function login(string $nit, string $password, ?string $sedeId = null): array
    {
        $sedes = $this->consultarSedes($nit);

        if (empty($sedes)) {
            return [
                'success' => false,
                'message' => 'No se encontraron sedes para el NIT proporcionado.',
                'sedes' => [],
            ];
        }

        if ($sedeId === null) {
            return [
                'success' => true,
                'step' => 'select_sede',
                'sedes' => $sedes,
            ];
        }

        $url = "{$this->baseUrl}/ObtenerTokenAutenticacion";

        Log::info('[EPS] Autenticando', [
            'url' => $url,
            'nit_prestador' => $nit,
            'id_sede' => $sedeId,
        ]);

        try {
            $response = $this->http()->post($url, [
                'nit_prestador' => $nit,
                'id_sede' => $sedeId,
                'contrasena' => $password,
            ]);

            Log::info('[EPS] Respuesta autenticación', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['token'] ?? null;

                return [
                    'success' => true,
                    'step' => 'authenticated',
                    'token' => $this->token,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al autenticar. Status: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('[EPS] Excepción autenticando', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ];
        }
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
