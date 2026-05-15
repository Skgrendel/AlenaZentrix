<?php

namespace App\Services\coordinador;

use App\Models\reportes;
use App\Models\surtigas;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio mejorado para consultas GIS con mejor debugging
 */
class DataGisServicesImproved
{
    private string $baseUrl = 'https://arcgisportal.surtigas.com.co/geaserver/rest/services/Ingenieria/FC_PTUSUARIOS/MapServer/0/query';

    private DataGisServicesToken $tokenService;

    public function __construct()
    {
        $this->tokenService = new DataGisServicesToken;
    }

    private array $headers = [
        'accept' => '*/*',
        'accept-language' => 'es-419,es;q=0.9,en;q=0.8',
        'priority' => 'u=1, i',
        'referer' => 'https://arcgisportal.surtigas.com.co/geaportal/apps/webappviewer/index.html?id=7f01784d858d43f49acd9fd5bd4b3123',
        'sec-ch-ua' => '"Chromium";v="136", "Google Chrome";v="136", "Not.A/Brand";v="99"',
        'sec-ch-ua-mobile' => '?0',
        'sec-ch-ua-platform' => '"Windows"',
        'sec-fetch-dest' => 'empty',
        'sec-fetch-mode' => 'cors',
        'sec-fetch-site' => 'same-origin',
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
    ];

    /**
     * Método de consulta mejorado con logging detallado
     */
    public function DataGisDebug($id)
    {
        try {
            $reportes = reportes::where('id', $id)->first();
            $surtigas = surtigas::where('id', $reportes->surtigas_id)->first();

            Log::info('=== GIS DEBUG START ===');
            Log::info("Reporte ID: $id");
            Log::info('Contrato: '.$surtigas->contrato);

            // Obtener token
            $token = $this->tokenService->getToken();
            Log::info('Token obtenido (primeros 50 chars): '.substr($token, 0, 50));

            if (! $token) {
                throw new \Exception('No se pudo obtener token');
            }

            // Construir URL
            $fields = 'OBJECTID,ORDEN,RID,OBJECTID_1,DEPARTAMENTO,LOCALIDAD,NOMBRE,ADDRESS_ID,ID_PREMISE,NUP,DIRECCION,TAG,ANILLADO,TIPOPREDIO,DESCRIPCION,BARRIO,NOMBREBARRIO,CATEGORIA,DESCATEGORIA,ESTRATO,CICLO,PRODUCT_ID,PRODUCT_STATUS_ID,ESTADOPRODUCTO,SUBSCRIPTION_ID,DESCESTADOCORTE,CODIDOESTADOCORTE,NOMBREUSUARIO,APELLIDO,SERIEMEDIDOR_ANTERIOR,FECHA_ANTERIOR,SERIEMEDIDOR_ACTUAL,MES1,MES2,MES3,MES4,MES5,MES6,ORIG_FID';

            // OPCIÓN 1: Token en query string (método original)
            $url1 = "{$this->baseUrl}?f=json&where=SUBSCRIPTION_ID%20%3D%20{$surtigas->contrato}&returnGeometry=true&spatialRel=esriSpatialRelIntersects&outFields={$fields}&outSR=102100&resultOffset=0&resultRecordCount=1000&token={$token}";

            Log::info('URL con token (primeros 200 chars): '.substr($url1, 0, 200));

            // Hacer la consulta
            $response = Http::withoutVerifying()
                ->withHeaders($this->headers)
                ->withOptions([
                    'version' => 2.0,
                ])
                ->get($url1);

            Log::info('Status HTTP: '.$response->status());
            Log::info('Headers respuesta: '.json_encode($response->headers()));

            if ($response->failed()) {
                Log::error('Request fallido. Status: '.$response->status());
                Log::error('Body: '.$response->body());

                return [
                    'error' => 'Error al consultar el servicio GIS. Status: '.$response->status(),
                    'debug' => [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500),
                    ],
                ];
            }

            $data = $response->json();
            Log::info('Respuesta JSON: '.json_encode($data));

            if (isset($data['error'])) {
                Log::error('Error en respuesta GIS: '.json_encode($data['error']));

                return [
                    'error' => $data['error']['message'] ?? 'Error desconocido',
                    'debug' => $data['error'],
                ];
            }

            if (! $data || ! isset($data['features'][0])) {
                Log::error('No se encontraron features en la respuesta');

                return [
                    'error' => 'No se encontraron datos para el contrato proporcionado.',
                    'debug' => ['features_count' => count($data['features'] ?? [])],
                ];
            }

            Log::info('✓ Consulta exitosa');
            Log::info('=== GIS DEBUG END ===');

            $attributes = $data['features'][0]['attributes'];

            return [
                'success' => true,
                'info' => [
                    'direccion' => $attributes['DIRECCION'] ?? '',
                    'cliente' => ($attributes['NOMBREUSUARIO'] ?? '').' '.($attributes['APELLIDO'] ?? ''),
                    'medidor' => $attributes['SERIEMEDIDOR_ACTUAL'] ?? '',
                    'contrato' => $attributes['SUBSCRIPTION_ID'] ?? '',
                ],
                'debug' => [
                    'token_used' => substr($token, 0, 50).'...',
                    'url_used' => substr($url1, 0, 100).'...',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('ERROR en DataGisDebug: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return [
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ];
        }
    }
}
