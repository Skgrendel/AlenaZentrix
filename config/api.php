<?php

return [
    /*
    | Versión mínima de la app móvil capaz de atender los subtipos nuevos de Auditoría de Campo.
    | La app envía la cabecera X-App-Version (semver).
    */
    'min_version_subtipos_nuevos' => env('API_MIN_VERSION_SUBTIPOS_NUEVOS', '1.1.0'),

    'subtipos_nuevos' => [
        'visita_calidad_suspensiones',
        'visita_calidad_medidores_digitales',
    ],

    'dias_completadas' => 90,
];
