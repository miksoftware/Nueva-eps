<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== CONSULTANDO API EPS ===\n\n";

$nit = '890907215';
$url = "https://api.referencias.nuevaeps.com.co/ConsultarSedesPorNITPrestador?nit_prestador={$nit}";

echo "URL: {$url}\n\n";

try {
    $response = Http::withOptions([
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
    ->timeout(30)
    ->get($url);

    echo "Status: " . $response->status() . "\n";
    echo "Headers:\n";
    foreach ($response->headers() as $key => $values) {
        echo "  {$key}: " . implode(', ', $values) . "\n";
    }
    echo "\nBody (raw):\n";
    echo $response->body() . "\n";

    echo "\nBody (json decoded):\n";
    $json = $response->json();
    print_r($json);

    if (is_array($json) && count($json) > 0) {
        echo "\n\nFirst element keys:\n";
        if (isset($json[0])) {
            print_r(array_keys($json[0]));
        } else {
            print_r(array_keys($json));
        }
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
