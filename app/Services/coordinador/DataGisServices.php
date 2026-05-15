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
        'Cookie' => 'AGS_ROLES="i96c/mmLorHlQpzmSue+ynS01g38XzWRs3bCji9jmlYtQYsgn+362nhRZI6qeQxH43OTAG6QyXzlprHFVVAM/g; esri_aopc=Yi0Y0Adj5UactyJn52KPjIcrLt6jIQo8kjLnkHK0lVIk8glqViOOnXLFuBJirQzjDr9lMeqybLtPgUjYH42uA-tpcJeDEbI8Dzr9n0c1FECF9cDeb--B8PfM7qq0vZKK-T3FrTXkCUCmaGF-VEGTy7w9FJACVF3FLI-CuxymitR_EM_WXLiHbDoFH-s75XfDPmqDEt_kb8mlCfqlvq8-4fnMEHNLXAov617EOI-V49vEt1iE9xBBU4X9i-NOVGFIlGrbdU5FuEwWOHtLE6n6nkUKJS654xnOK6GdguyKQ-Co7n6vcEw1jpMKvTjFZf0jtxCV7qmMiNosa4X8PAhLB_HGvRMHUzY_OrvwD5mmTr1cXwY-9WAIDJMiNnfznRFLTC7arXfscsSnK1SI1e51GuNJieXl7O7Z6EXhkRX6lladx3KoObo032STvsTKg2zV78ajbUMa5wUKGtgV61j3DHDTkIFRIn804ZIPiD8lIVmYiSyw7q-kqyfjQ4zd_mm2sM2mm9fooZmxoWeNhh6wuDRpHbTMxI0t0d0JlrAYMSe0KtUNrsLu5S_orZI6JcwZGXFxa2FVMgjRjCvZ1eXCRnsc7bY_wi8NzI2jFwdtNbSOsQHvqY7uu90T40CUVyHf; esri_auth=%7B%22portalApp%22%3Atrue%2C%22email%22%3A%22riesgosproderi%22%2C%22token%22%3A%22eFfNMlvqhk5_p00rjom9llmSWPkSpkI6AK9cgidnu9a9pM0KZVz98WD7q1B6iC6pyoTFF9YJAwSsJMZ93xoM9DkSn4af6SrVJM0agxyAdhP4b3eQ5QTZgoH1L-zZ10rWhZOesR3VPvBnVgonfKCtxjznT-BCjsHkc-tYLF8Yius0yhFmOVv6nZ3GyvbTkAE1msgOrhbD2kVgob_AGIHMlPCqwKmTlYP5p8dQOBTT6vk.%22%2C%22expires%22%3A1749055327383%2C%22allSSL%22%3Atrue%2C%22persistent%22%3Atrue%2C%22created%22%3A1747845787383%2C%22culture%22%3A%22es-es%22%2C%22region%22%3Anull%2C%22accountId%22%3A%220123456789ABCDEF%22%2C%22role%22%3A%22org_user%22%2C%22customBaseUrl%22%3A%22arcgisportal.surtigas.com.co%2Fgeaportal%22%7D',
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
                ->withOptions([
                    'version' => 2.0,
                ])
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
        } catch (\Exception $e) {
            return [
                'error' => 'Se produjo un error al intentar acceder al servicio : ', // . $e->getMessage()
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
