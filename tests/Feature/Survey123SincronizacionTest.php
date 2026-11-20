<?php

use App\Models\Company;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Services\survey123\Survey123Client;
use App\Services\survey123\Survey123Mapeador;
use App\Services\survey123\Survey123Sincronizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const SERVICIO = 'https://services.test/FeatureServer/0';

function metadatosSurvey(): array
{
    return [
        'objectIdField' => 'OBJECTID',
        'globalIdField' => 'GlobalID',
        'editFieldsInfo' => ['editDateField' => 'EditDate', 'creationDateField' => 'CreationDate'],
        'fields' => [
            ['name' => 'OBJECTID', 'alias' => 'ObjectID'],
            ['name' => 'GlobalID', 'alias' => 'GlobalID'],
            ['name' => 'fecha', 'alias' => 'Fecha de registro'],
            ['name' => 'tecnico', 'alias' => 'Nombre del Técnico'],
            ['name' => 'contrato', 'alias' => 'Contrato'],
            ['name' => 'ot', 'alias' => 'Orden de Trabajo'],
            ['name' => 'categoria', 'alias' => 'Categoría en campo', 'domain' => ['codedValues' => [
                ['code' => 'residencial', 'name' => 'Residencial'],
            ]]],
            ['name' => 'serie_medidor', 'alias' => 'Serie de medidor instalado'],
            ['name' => 'lectura', 'alias' => 'Lectura del medidor instalado'],
            ['name' => 'actividad', 'alias' => '¿Qué actividad va a ejecutar?', 'domain' => ['codedValues' => [
                ['code' => 'suspension', 'name' => 'Suspensión'],
            ]]],
            ['name' => 'ejecutada', 'alias' => '¿Se pudo ejecutar la suspensión?', 'domain' => ['codedValues' => [
                ['code' => 'si', 'name' => 'Sí'], ['code' => 'no', 'name' => 'No'],
            ]]],
            ['name' => 'metodo', 'alias' => '¿Método de suspensión instalado?', 'domain' => ['codedValues' => [
                ['code' => 'tapon_acometida', 'name' => 'Taponamiento de acometida'],
            ]]],
            ['name' => 'sonda_reconexion', 'alias' => 'Serie de la sonda utilizada'],
            ['name' => 'sonda_suspension', 'alias' => 'Serie de la sonda utilizada'],
            ['name' => 'justificacion', 'alias' => 'JUSTIFICACIÓN'],
            ['name' => 'EditDate', 'alias' => 'EditDate'],
            ['name' => 'CreationDate', 'alias' => 'CreationDate'],
        ],
    ];
}

function featureSurvey(int $objectId = 2, int $editMs = 1_763_600_000_000): array
{
    return [
        'attributes' => [
            'OBJECTID' => $objectId,
            'GlobalID' => "gid-{$objectId}",
            'fecha' => 1_762_800_000_000,
            'tecnico' => 'Luis Quiroga',
            'contrato' => 296192.0,
            'ot' => 319890863,
            'categoria' => 'residencial',
            'serie_medidor' => 981797,
            'lectura' => 6972.0,
            'actividad' => 'suspension',
            'ejecutada' => 'si',
            'metodo' => 'tapon_acometida',
            'sonda_suspension' => null,
            'justificacion' => 'Se realiza suspension desde acometida',
            'EditDate' => $editMs,
            'CreationDate' => 1_762_800_000_000,
        ],
        'geometry' => ['x' => -75.488048, 'y' => 10.407333],
    ];
}

function simularArcGis(array $features, array $adjuntos = []): void
{
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['token' => 'tok-prueba']),
        SERVICIO => Http::response(metadatosSurvey()),
        SERVICIO.'/query' => Http::response(['features' => $features]),
        SERVICIO.'/queryAttachments' => Http::response(['attachmentGroups' => $adjuntos]),
        SERVICIO.'/*/attachments/*' => Http::response('contenido-de-foto'),
    ]);
}

function clienteSurvey(): Survey123Client
{
    return new Survey123Client('usuario', 'clave', 'https://portal.test', SERVICIO);
}

test('el mapeador traduce un registro de Survey123 a nuestras columnas', function () {
    $datos = (new Survey123Mapeador(metadatosSurvey()))->mapear(featureSurvey());

    expect($datos['global_id'])->toBe('gid-2')
        ->and($datos['tecnico'])->toBe('Luis Quiroga')
        ->and($datos['contrato'])->toBe('296192')
        ->and($datos['orden_trabajo'])->toBe('319890863')
        ->and($datos['categoria'])->toBe('residencial')
        ->and($datos['serie_medidor'])->toBe('981797')
        ->and($datos['lectura_medidor'])->toBe('6972')
        ->and($datos['actividad'])->toBe('suspension')
        ->and($datos['suspension_ejecutada'])->toBeTrue()
        ->and($datos['metodo_suspension'])->toBe('taponamiento_acometida')
        ->and($datos['latitud'])->toBe(10.407333)
        ->and($datos['longitud'])->toBe(-75.488048)
        ->and($datos['fecha_registro'])->not->toBeNull()
        ->and($datos['payload'])->toHaveKey('ot');
});

test('con alias repetidos, la sonda de la suspensión es la última pregunta del formulario', function () {
    $feature = featureSurvey();
    $feature['attributes']['sonda_reconexion'] = 111;
    $feature['attributes']['sonda_suspension'] = 222;

    $datos = (new Survey123Mapeador(metadatosSurvey()))->mapear($feature);

    expect($datos['serie_sonda'])->toBe('222');
});

test('sincronizar crea registros y copia las fotos a S3', function () {
    Storage::fake('s3');
    $empresa = Company::factory()->create();
    simularArcGis([featureSurvey()], [
        ['parentObjectId' => 2, 'attachmentInfos' => [
            ['id' => 10, 'globalId' => 'a-10', 'name' => 'foto_fachada-20261110-152207.jpg', 'contentType' => 'image/jpeg', 'size' => 17, 'keywords' => 'foto_fachada'],
            ['id' => 11, 'globalId' => 'a-11', 'name' => 'foto_medidor-20261110-152222.jpg', 'contentType' => 'image/jpeg', 'size' => 17, 'keywords' => 'foto_medidor'],
        ]],
    ]);

    $resumen = (new Survey123Sincronizador(clienteSurvey()))->sincronizar($empresa->id);

    expect($resumen)->toMatchArray(['nuevos' => 1, 'fotos' => 2, 'errores' => 0]);

    $registro = SurveyRegistro::query()->withoutGlobalScopes()->firstOrFail();
    expect($registro->company_id)->toBe($empresa->id)
        ->and($registro->adjuntos)->toHaveCount(2);

    $foto = SurveyAdjunto::query()->where('campo', 'foto_fachada')->firstOrFail();
    Storage::disk('s3')->assertExists($foto->ruta);
    expect($foto->hash)->toBe(sha1('contenido-de-foto'));
});

test('sincronizar dos veces no duplica registros ni fotos', function () {
    Storage::fake('s3');
    $empresa = Company::factory()->create();
    $adjuntos = [['parentObjectId' => 2, 'attachmentInfos' => [
        ['id' => 10, 'name' => 'foto_fachada-1.jpg', 'keywords' => 'foto_fachada'],
    ]]];
    simularArcGis([featureSurvey()], $adjuntos);

    $sincronizador = new Survey123Sincronizador(clienteSurvey());
    $sincronizador->sincronizar($empresa->id);
    $segunda = $sincronizador->sincronizar($empresa->id);

    expect($segunda)->toMatchArray(['nuevos' => 0, 'sin_cambios' => 1, 'fotos' => 0])
        ->and(SurveyRegistro::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(SurveyAdjunto::query()->count())->toBe(1);
});

test('un registro editado en el origen se actualiza sin perder su estado', function () {
    Storage::fake('s3');
    $empresa = Company::factory()->create();

    simularArcGis([featureSurvey(2, 1_763_600_000_000)]);
    (new Survey123Sincronizador(clienteSurvey()))->sincronizar($empresa->id, conFotos: false);

    SurveyRegistro::query()->withoutGlobalScopes()->update(['estado' => 'asignado']);

    $editado = featureSurvey(2, 1_763_700_000_000);
    $editado['attributes']['lectura'] = 7000.0;
    Http::swap(new Factory);
    simularArcGis([$editado]);

    $resumen = (new Survey123Sincronizador(clienteSurvey()))->sincronizar($empresa->id, conFotos: false);

    $registro = SurveyRegistro::query()->withoutGlobalScopes()->firstOrFail();
    expect($resumen['actualizados'])->toBe(1)
        ->and($registro->lectura_medidor)->toBe('7000')
        ->and($registro->estado->value)->toBe('asignado');
});

test('el cliente informa el error cuando ArcGIS rechaza las credenciales', function () {
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['error' => [
            'code' => 400, 'message' => 'Unable to generate token.', 'details' => ['Invalid username or password.'],
        ]]),
        '*/sharing/rest/oauth2/authorize*' => Http::response('<html>sin estado</html>'),
    ]);

    expect(fn () => clienteSurvey()->metadatos())
        ->toThrow(RuntimeException::class, 'Invalid username or password');
});

test('el token viaja en la cabecera y no en la URL', function () {
    simularArcGis([]);

    clienteSurvey()->metadatos();

    Http::assertSent(fn ($request) => $request->url() === SERVICIO
        && $request->hasHeader('X-Esri-Authorization', 'Bearer tok-prueba')
        && ! str_contains($request->url(), 'token'));
});

test('si generateToken falla, el cliente inicia sesión por OAuth como el navegador', function () {
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['error' => ['code' => 400, 'message' => 'Unable to generate token.']]),
        '*/sharing/rest/oauth2/authorize*' => Http::response('{"oauth_state":"estado-123"}'),
        '*/sharing/oauth2/signin' => Http::response('', 302, ['Location' => 'https://survey123.arcgis.com/surveys#access_token=tok-oauth&expires_in=7200']),
        SERVICIO => Http::response(metadatosSurvey()),
    ]);

    clienteSurvey()->metadatos();

    Http::assertSent(fn ($request) => $request->url() === SERVICIO
        && $request->hasHeader('X-Esri-Authorization', 'Bearer tok-oauth'));
});

test('si tampoco funciona el OAuth, se informa el error de generateToken', function () {
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['error' => ['code' => 400, 'message' => 'Unable to generate token.', 'details' => ['Invalid username or password.']]]),
        '*/sharing/rest/oauth2/authorize*' => Http::response('<html>sin estado</html>'),
    ]);

    expect(fn () => clienteSurvey()->metadatos())
        ->toThrow(RuntimeException::class, 'Invalid username or password');
});
