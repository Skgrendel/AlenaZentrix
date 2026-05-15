<?php

namespace App\Services\coordinador;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataGisServicesToken
{
    private ?string $proxy;

    public function __construct()
    {
        $server = config('services.proxy.server');

        $this->proxy = $server
            ? 'http://'.config('services.proxy.user').':'.config('services.proxy.pass').'@'.$server.':'.config('services.proxy.port')
            : null;
    }

    /** @return array<string, mixed> */
    private function proxyOptions(): array
    {
        if (! $this->proxy) {
            return [];
        }

        return [
            'proxy' => [
                'http' => $this->proxy,
                'https' => $this->proxy,
            ],
        ];
    }

    public function DataGis()
    {
        try {
            $Gen_token = 'https://arcgisportal.surtigas.com.co/geaportal/sharing/rest/oauth2/authorize?client_id=arcgisonline&display=default&response_type=token&state=%7B%22returnUrl%22%3A%22https%3A%2F%2Farcgisportal.surtigas.com.co%2Fgeaportal%2Fapps%2Fwebappviewer%2Findex.html%3Fid%3D7f01784d858d43f49acd9fd5bd4b3123%22%2C%22useLandingPage%22%3Afalse%7D&expiration=20160&locale=es-es&redirect_uri=https%3A%2F%2Farcgisportal.surtigas.com.co%2Fgeaportal%2Fhome%2Faccountswitcher-callback.html&force_login=false&hideCancel=true&showSignupOption=true&canHandleCrossOrgSignIn=true&signuptype=esri';
            $response = Http::withoutVerifying()
                ->withHeaders([
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
                ])
                ->withOptions(array_merge(['version' => 2.0, 'force_ip_resolve' => 'v4'], $this->proxyOptions()))
                ->get($Gen_token);

            // Check if the response is successful
            if ($response->successful()) {
                // Parse the response body to extract the token
                $responseBody = $response->body();
                preg_match('/"oauth_state"\s*:\s*"([^"]+)"/', $responseBody, $matches);
                if (isset($matches[1])) {
                    $token = $matches[1];

                    return $token; // Return the token
                } else {
                    Log::error('Token not found in response body.');

                    return null; // Token not found
                }
            } else {
                Log::error('Failed to retrieve token from DataGis Service (Datagis).', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers(),
                ]);

                return null; // Request failed
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Se produjo un error al intentar acceder al servicio : ',  // . $e->getMessage()
            ];
        }
    }

    public function logingis()
    {

        $login = $this->DataGis();

        if ($login) {
            $url = 'https://arcgisportal.surtigas.com.co/geaportal/sharing/oauth2/signin';
            // Use the token in the request
            $response = Http::withoutVerifying()->withHeaders([
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
            ])
                ->withOptions(array_merge(['version' => 2.0, 'force_ip_resolve' => 'v4'], $this->proxyOptions()))
                ->withoutRedirecting()
                ->asForm()
                ->post($url, [
                    'oauth_state' => $login,
                    'authorize' => true,
                    'username' => env('KEY_GIS_USER'),
                    'password' => env('KEY_GIS_PASSWORD'),
                ]);

            // redirect to the URL
            $gisurl = $response->handlerStats()['redirect_url'] ?? null;
            $parsedUrl = parse_url($gisurl, PHP_URL_FRAGMENT);
            if ($parsedUrl) {
                parse_str($parsedUrl, $params);
                $token = $params['access_token'] ?? null;
                if ($token) {
                    return $token; // Return the token
                } else {
                    Log::error('Access token not found in the response.');

                    return null; // Access token not found
                }
            } else {
                Log::error('Failed to parse the redirect URL.');

                return null; // Redirect URL parsing failed
            }
        }

        return $this; // Return the token
    }

    public function getToken()
    {
        // Verificar si el token está en caché y aún es válido
        $cache = Cache::get('gis_token');
        if ($cache) {
            Log::info('Retornando token GIS cacheado.');

            return $cache; // Retornar token cacheado si disponible
        }

        try {
            // Obtener nuevo token
            $token = $this->logingis();

            if (! $token) {
                throw new \Exception('No se pudo obtener el token del servicio GIS');
            }

            // URL para generar el token con expiración
            $url = "https://arcgisportal.surtigas.com.co/geaportal/sharing/generateToken?request=getToken&serverUrl=https%3A%2F%2Farcgisportal.surtigas.com.co%2Fgeaportal%2Fsharing%2Fservers%2Fa4c5837128a3482f89823d843cc26dde%2Frest%2Fservices%2FIngenieria%2FRedGas%2FFeatureServer%2F34&token=$token&referer=arcgisportal.surtigas.com.co&f=json";

            $response = Http::withoutVerifying()
                ->withHeaders([
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
                ])
                ->withOptions(array_merge(['version' => 2.0, 'force_ip_resolve' => 'v4'], $this->proxyOptions()))
                ->get($url);

            if (! $response->successful()) {
                throw new \Exception('Error en respuesta del servidor: '.$response->status());
            }

            $data = $response->json();

            if (! isset($data['token'])) {
                throw new \Exception('Token no encontrado en la respuesta');
            }

            // Calcular tiempo de expiración (por defecto 60 minutos si no viene en la respuesta)
            $expiresAt = isset($data['expires'])
                ? Carbon::createFromTimestampMs($data['expires'])
                : Carbon::now()->addMinutes(60);

            // Cachear el token con su tiempo de expiración
            Cache::put('gis_token', $data['token'], $expiresAt);

            Log::info('Token GIS actualizado y cacheado.');

            return $data['token'];

        } catch (\Exception $e) {
            Log::error('Error al obtener token GIS: '.$e->getMessage());

            return null;
        }
    }
}
