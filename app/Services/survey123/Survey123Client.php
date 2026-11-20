<?php

namespace App\Services\survey123;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de solo lectura del Feature Service de ArcGIS donde Survey123 guarda las respuestas.
 * El token viaja en la cabecera, nunca en la URL.
 */
class Survey123Client
{
    private const TAMANO_PAGINA = 1000;

    private ?string $token = null;

    public function __construct(
        private readonly string $usuario,
        private readonly string $password,
        private readonly string $portalUrl,
        private readonly string $serviceUrl,
    ) {}

    public static function desdeConfig(): self
    {
        $config = config('services.survey123');

        if (blank($config['usuario'] ?? null) || blank($config['password'] ?? null)) {
            throw new RuntimeException('Faltan SURVEY123_USUARIO y SURVEY123_PASSWORD en el .env.');
        }

        return new self($config['usuario'], $config['password'], $config['portal_url'], $config['service_url']);
    }

    /** @return array<string, mixed> */
    public function metadatos(): array
    {
        return $this->peticion()->asForm()->post($this->serviceUrl, ['f' => 'json'])->json() ?? [];
    }

    /**
     * Registros en lotes, ordenados por el campo identificador para paginar de forma estable.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function registros(string $where, string $campoId): \Generator
    {
        $offset = 0;

        do {
            $respuesta = $this->consultar('query', [
                'where' => $where,
                'outFields' => '*',
                'returnGeometry' => 'true',
                'outSR' => 4326,
                'orderByFields' => $campoId,
                'resultOffset' => $offset,
                'resultRecordCount' => self::TAMANO_PAGINA,
            ]);

            $features = $respuesta['features'] ?? [];

            foreach ($features as $feature) {
                yield $feature;
            }

            $offset += count($features);
        } while (($respuesta['exceededTransferLimit'] ?? false) && $features !== []);
    }

    /**
     * Adjuntos agrupados por el ObjectID del registro al que pertenecen.
     *
     * @param  array<int, int|string>  $objectIds
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    public function adjuntos(array $objectIds): array
    {
        if ($objectIds === []) {
            return [];
        }

        $respuesta = $this->consultar('queryAttachments', ['objectIds' => implode(',', $objectIds)]);

        $porRegistro = [];

        foreach ($respuesta['attachmentGroups'] ?? [] as $grupo) {
            $porRegistro[$grupo['parentObjectId']] = $grupo['attachmentInfos'] ?? [];
        }

        return $porRegistro;
    }

    public function descargar(int|string $objectId, int|string $attachmentId): string
    {
        $respuesta = $this->peticion()->get("{$this->serviceUrl}/{$objectId}/attachments/{$attachmentId}");

        if (! $respuesta->successful()) {
            throw new RuntimeException("No se pudo descargar el adjunto {$attachmentId} del registro {$objectId}.");
        }

        return $respuesta->body();
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    private function consultar(string $operacion, array $parametros): array
    {
        $respuesta = $this->peticion()
            ->asForm()
            ->post("{$this->serviceUrl}/{$operacion}", $parametros + ['f' => 'json'])
            ->json() ?? [];

        if (isset($respuesta['error'])) {
            throw new RuntimeException('ArcGIS respondió con error: '.($respuesta['error']['message'] ?? 'desconocido'));
        }

        return $respuesta;
    }

    private function peticion(): PendingRequest
    {
        return Http::timeout(60)
            ->retry(2, 500)
            ->withHeaders(['X-Esri-Authorization' => 'Bearer '.$this->token()]);
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $respuesta = Http::timeout(30)
            ->asForm()
            ->post(rtrim($this->portalUrl, '/').'/sharing/rest/generateToken', [
                'username' => $this->usuario,
                'password' => $this->password,
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
                'f' => 'json',
            ])
            ->json() ?? [];

        if (isset($respuesta['token'])) {
            return $this->token = $respuesta['token'];
        }

        $error = $respuesta['error'] ?? [];
        $detalle = implode(' ', (array) ($error['details'] ?? []));

        $token = $this->tokenPorOauth();

        if ($token === null) {
            throw new RuntimeException(trim('No se pudo autenticar en ArcGIS (código '.($error['code'] ?? '?').'): '.($error['message'] ?? 'respuesta sin token').' '.$detalle.' El inicio de sesión OAuth tampoco funcionó.'));
        }

        return $this->token = $token;
    }

    /**
     * Inicio de sesión por OAuth, igual al del navegador: pide la autorización, envía el usuario
     * y la contraseña y lee el token de la redirección. Sirve cuando generateToken no está permitido.
     */
    private function tokenPorOauth(): ?string
    {
        $redirect = 'https://survey123.arcgis.com/surveys';
        $portal = rtrim($this->portalUrl, '/');

        $autorizacion = Http::timeout(30)->get("{$portal}/sharing/rest/oauth2/authorize", [
            'client_id' => 'survey123hub',
            'response_type' => 'token',
            'redirect_uri' => $redirect,
            'expiration' => 120,
        ]);

        if (! preg_match('/"oauth_state"\s*:\s*"([^"]+)"/', $autorizacion->body(), $coincidencia)) {
            return null;
        }

        $inicio = Http::timeout(30)
            ->withoutRedirecting()
            ->asForm()
            ->post("{$portal}/sharing/oauth2/signin", [
                'oauth_state' => $coincidencia[1],
                'authorize' => true,
                'username' => $this->usuario,
                'password' => $this->password,
            ]);

        $fragmento = parse_url($inicio->header('Location'), PHP_URL_FRAGMENT);

        if (! $fragmento) {
            return null;
        }

        parse_str($fragmento, $parametros);

        return $parametros['access_token'] ?? null;
    }
}
