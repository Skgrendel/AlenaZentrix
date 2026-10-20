<?php

namespace App\Services\coordinador;

use App\Models\GisToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataGisServices
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

    private function getToken(): string
    {
        // ESTRATEGIA: Generar token fresco siempre
        // El token en BD podría estar expirado sin que nos demos cuenta

        try {
            Log::info('Intentando generar token GIS fresco...');
            $tokenService = $this->tokenService;
            $token = $tokenService->getToken();

            if (! $token) {
                Log::warning('No se pudo generar token fresco, intentando fallback a BD');
                throw new \Exception('No se pudo obtener token del servicio GIS');
            }

            // Guardar en BD para auditoría y fallback
            GisToken::updateOrCreate(
                ['activo' => true],
                [
                    'token' => $token,
                    'descripcion' => 'Token GIS - Generado automáticamente',
                    'expires_at' => Carbon::now()->addMinutes(55),
                ]
            );

            Log::info('✓ Token GIS fresco generado y guardado en BD');

            return $token;

        } catch (\Exception $e) {
            Log::error('Error al generar token fresco: '.$e->getMessage());

            // FALLBACK: Si falla la generación, usar el de BD como último recurso
            Log::warning('Intentando fallback a token de BD...');
            $tokenFromDB = GisToken::getActiveToken();

            if ($tokenFromDB) {
                Log::warning('Usando token de BD como fallback (puede estar expirado)');

                return $tokenFromDB;
            }

            Log::error('No hay token disponible ni en servicio ni en BD');
            throw new \Exception('No se pudo obtener token GIS de ninguna fuente: '.$e->getMessage());
        }
    }

    public function consultarContrato(string $contrato): array
    {
        try {
            $token = $this->getToken();
            $url = $this->buildGisUrl($contrato, $token);

            $response = Http::withoutVerifying()
                ->withHeaders($this->headers)
                ->withOptions(array_merge(['version' => 2.0], $this->tokenService->proxyOptions()))
                ->get($url);

            if ($response->failed()) {
                return ['error' => 'Error al consultar el servicio GIS. Por favor, inténtelo más tarde.'];
            }

            $data = $response->json();

            if (isset($data['error'])) {
                return ['error' => $data['error']['message']];
            }

            if (! $data || ! isset($data['features'][0])) {
                return ['error' => 'No se encontraron datos para el contrato proporcionado.'];
            }

            $attributes = $data['features'][0]['attributes'] ?? [];
            $geometry = $data['features'][0]['geometry'] ?? null;

            if (! isset($geometry['x'], $geometry['y'])) {
                return ['error' => 'El contrato existe en GIS pero no tiene coordenadas registradas.'];
            }

            [$lat, $lng] = $this->convertWebMercatorToLatLng($geometry['x'], $geometry['y']);

            $nombreUsuario = trim(($attributes['NOMBREUSUARIO'] ?? '').' '.($attributes['APELLIDO'] ?? ''));
            $estadoCorte = isset($attributes['DESCESTADOCORTE'])
                ? trim((string) preg_replace('/[-\s]+$/', '', $attributes['DESCESTADOCORTE']))
                : null;

            return [
                'titular' => [
                    'nombre' => $nombreUsuario ?: null,
                    'zona' => $attributes['NOMBRE'] ?? null,
                    'nup' => $attributes['NUP'] ?? null,
                    'contrato' => $attributes['SUBSCRIPTION_ID'] ?? null,
                    'orden' => $attributes['ORDEN'] ?? null,
                ],
                'ubicacion' => [
                    'municipio' => $attributes['NOMBRE'] ?? null,
                    'direccion' => $attributes['DIRECCION'] ?? null,
                    'barrio' => $attributes['NOMBREBARRIO'] ?? $attributes['BARRIO'] ?? null,
                    'tag' => $attributes['TAG'] ?? null,
                ],
                'servicio' => [
                    'estado' => $attributes['ESTADOPRODUCTO'] ?? null,
                    'estado_corte' => $estadoCorte ?: null,
                    'descripcion' => $attributes['DESCRIPCION'] ?? null,
                    'categoria' => $attributes['DESCATEGORIA'] ?? $attributes['CATEGORIA'] ?? null,
                    'estrato' => $attributes['ESTRATO'] ?? null,
                    'ciclo' => $attributes['CICLO'] ?? null,
                    'anillado' => $attributes['ANILLADO'] ?? null,
                ],
                'medidor' => [
                    'serie_actual' => $attributes['SERIEMEDIDOR_ACTUAL'] ?? null,
                    'serie_anterior' => $attributes['SERIEMEDIDOR_ANTERIOR'] ?? null,
                    'fecha_anterior' => $this->formatTimestamp($attributes['FECHA_ANTERIOR'] ?? null),
                ],
                'consumos' => [
                    'mes1' => $attributes['MES1'] ?? null,
                    'mes2' => $attributes['MES2'] ?? null,
                    'mes3' => $attributes['MES3'] ?? null,
                    'mes4' => $attributes['MES4'] ?? null,
                    'mes5' => $attributes['MES5'] ?? null,
                    'mes6' => $attributes['MES6'] ?? null,
                ],
                'coordenadas' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'maps_url' => "https://www.google.com/maps/place/{$lat},{$lng}",
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Error en consultarContrato GIS: '.$e->getMessage(), [
                'contrato' => $contrato,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['error' => 'Se produjo un error al intentar acceder al servicio GIS.'];
        }
    }

    public function DataGisubicacion(string $contrato)
    {
        try {

            // $loginSgt = new DataGisServicesToken();
            // $token =  $loginSgt->logingis()->getToken();
            // Token de acceso para el servicio GIS desde la base de datos
            $token = $this->getToken();

            // URL de consulta
            $url = $this->buildGisUrl($contrato, $token);

            $urlConsulta = Http::withoutVerifying()
                ->withHeaders($this->headers)
                ->withOptions(array_merge(['version' => 2.0], $this->tokenService->proxyOptions()))
                ->get($url);

            // Verificar el estado de la respuesta
            if ($urlConsulta->failed()) {
                return [
                    'error' => 'Error al consultar el servicio GIS. Por favor, inténtelo más tarde.',
                ];
            }

            // Decodificar la respuesta JSON
            $data = $urlConsulta->json();

            if (isset($data['error'])) {
                return [
                    'error' => $data['error']['message'],
                ];
            }

            if (! $data || ! isset($data['features'][0])) {
                return [
                    'error' => 'No se encontraron datos para el contrato proporcionado.',
                ];
            }

            $attributes = $data['features'][0]['attributes'];
            $geometry = $data['features'][0]['geometry'];

            // Convertir coordenadas de Web Mercator a latitud y longitud
            [$lat, $lng] = $this->convertWebMercatorToLatLng($geometry['x'], $geometry['y']);

            return [
                'geometry' => [
                    'link' => 'https://www.google.com/maps/place/'.$lat.','.$lng,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Error en DataGisubicacion GIS: '.$e->getMessage(), ['contrato' => $contrato]);

            return [
                'error' => 'Se produjo un error al intentar acceder al servicio GIS.',
            ];
        }
    }

    private function convertWebMercatorToLatLng($x, $y)
    {
        $lng = ($x / 6378137) * (180 / pi());
        $lat = (2 * atan(exp($y / 6378137)) - (pi() / 2)) * (180 / pi());

        return [$lat, $lng];
    }

    private function formatTimestamp($timestamp)
    {
        if (! $timestamp) {
            return null;
        }

        // Convertir milisegundos a segundos si es necesario
        $seconds = $timestamp > 9999999999 ? $timestamp / 1000 : $timestamp;

        return date('d/m/Y', intval($seconds));
    }

    private function buildGisUrl(string $contrato, string $token): string
    {
        $fields = 'OBJECTID,ORDEN,RID,OBJECTID_1,DEPARTAMENTO,LOCALIDAD,NOMBRE,ADDRESS_ID,ID_PREMISE,NUP,DIRECCION,TAG,ANILLADO,TIPOPREDIO,DESCRIPCION,BARRIO,NOMBREBARRIO,CATEGORIA,DESCATEGORIA,ESTRATO,CICLO,PRODUCT_ID,PRODUCT_STATUS_ID,ESTADOPRODUCTO,SUBSCRIPTION_ID,DESCESTADOCORTE,CODIDOESTADOCORTE,NOMBREUSUARIO,APELLIDO,SERIEMEDIDOR_ANTERIOR,FECHA_ANTERIOR,SERIEMEDIDOR_ACTUAL,MES1,MES2,MES3,MES4,MES5,MES6,ORIG_FID';

        return "{$this->baseUrl}?f=json&where=SUBSCRIPTION_ID%20%3D%20{$contrato}&returnGeometry=true&spatialRel=esriSpatialRelIntersects&outFields={$fields}&outSR=102100&resultOffset=0&resultRecordCount=1000&token={$token}";
    }
}
