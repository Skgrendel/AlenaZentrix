<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Portal GIS de Surtigas (consulta de datos del contrato).
    'gis' => [
        'usuario' => env('KEY_GIS_USER'),
        'password' => env('KEY_GIS_PASSWORD'),
        // Debe quedar en true. Solo se apaga (GIS_VERIFICAR_CERTIFICADO=false) como medida temporal
        // si el certificado del portal falla, y se vuelve a encender cuando se corrija.
        'verificar_certificado' => filter_var(env('GIS_VERIFICAR_CERTIFICADO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'survey123' => [
        'usuario' => env('SURVEY123_USUARIO'),
        'password' => env('SURVEY123_PASSWORD'),
        'portal_url' => env('SURVEY123_PORTAL_URL', 'https://www.arcgis.com'),
        // Disco donde se copian las fotos de los registros ('s3' en producción).
        'disco_fotos' => env('SURVEY123_DISCO_FOTOS', 's3'),
        // Minutos que deben pasar desde que termina una sincronización para poder lanzar otra desde la pantalla.
        'espera_sincronizacion_minutos' => (int) env('SURVEY123_ESPERA_MINUTOS', 10),

        // Sincronización automática: se salta sola si no hay usuario y contraseña configurados.
        'sincronizacion_automatica' => filter_var(env('SURVEY123_SYNC_AUTOMATICA', true), FILTER_VALIDATE_BOOLEAN),
        'sincronizacion_cada_minutos' => (int) env('SURVEY123_SYNC_CADA_MINUTOS', 60),

        // Empresa dueña de los registros. Si hay una sola empresa en el sistema no hace falta indicarla.
        'empresa_id' => env('SURVEY123_EMPRESA_ID'),

        'service_url' => env('SURVEY123_SERVICE_URL', 'https://services1.arcgis.com/7TRgQ327rtmQC0Ev/arcgis/rest/services/service_4397fd08af0043c8a7f7aa6eb5947eb8/FeatureServer/0'),
    ],

    // Revisión de fotos con IA (prueba en modo sombra: no cambia el resultado de la auditoría).
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'modelo' => env('OPENAI_MODELO_FOTOS', 'gpt-4o'),
        // Detalle con que el modelo mira cada foto: 'low' (barato) o 'high' (lee números de medidor).
        'detalle_imagen' => env('OPENAI_DETALLE_FOTOS', 'high'),
        // Modelos con visión que se ofrecen en la configuración, con su tarifa en USD por millón de tokens.
        // Las tarifas son de referencia para estimar costos: confirmarlas en la tarifa vigente del proveedor.
        'modelos' => [
            'gpt-4o-mini' => ['entrada' => 0.15, 'salida' => 0.60],
            'gpt-4o' => ['entrada' => 2.50, 'salida' => 10.00],
            'gpt-4.1-mini' => ['entrada' => 0.40, 'salida' => 1.60],
            'gpt-4.1' => ['entrada' => 2.00, 'salida' => 8.00],
        ],
    ],

];
