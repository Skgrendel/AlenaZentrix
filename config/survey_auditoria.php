<?php

/*
|--------------------------------------------------------------------------
| Auditoría de registros de Survey123 (suspensiones)
|--------------------------------------------------------------------------
| Reglas que se aplican a cada registro y sus umbrales. Cada regla se puede apagar
| poniendo su valor en false.
*/

return [

    /**
     * Zona horaria en la que se muestran las fechas de los registros (lista, detalle) y se escriben en el Excel.
     * La base guarda UTC; así se ve la misma hora que en el formulario de los técnicos.
     */
    'zona_horaria_excel' => 'America/Bogota',

    'reglas' => [
        'tecnico' => true,
        'contrato' => true,
        'orden_trabajo' => true,
        'categoria' => true,
        'serie_medidor' => true,
        'lectura' => true,
        'fecha' => true,
        'actividad' => true,
        'suspension' => true,
        'serie_sonda_caps' => true,
        'justificacion' => true,
        'fotos_minimas' => true,
        'fotos_repetidas' => true,
        'ubicacion' => true,
        'punto_repetido' => true,
    ],

    'parametros' => [
        // Dígitos esperados en la orden de trabajo y en el contrato.
        'ot_digitos' => 9,
        'contrato_digitos_min' => 4,
        'contrato_digitos_max' => 10,

        // Longitud razonable de la serie del medidor.
        'serie_medidor_min' => 5,
        'serie_medidor_max' => 12,

        // Lectura a partir de la cual se considera excesiva.
        'lectura_maxima' => 100000,

        // Horas máximas entre el registro de la visita y su envío.
        'horas_max_entre_registro_y_envio' => 24,

        // Fotos mínimas fijas (nombre de la pregunta en la encuesta => cantidad). La del medidor solo si se ejecutó.
        'fotos_minimas' => [
            'foto_fachada' => 3,
            'foto_medidor' => 1,
        ],

        // Desde esta fecha (UTC) la encuesta pide foto del IVT y tres de la actividad (antes, durante y después).
        // Es la última modificación del formulario; los registros anteriores traen una sola foto de actividad.
        'formulario_nuevo_desde' => '2026-11-17 13:21:00',

        // Fotos de actividad exigidas a los registros del formulario anterior.
        'foto_actividad_anterior_minimo' => 1,

        // Distancia (m) por debajo de la cual dos contratos distintos se consideran en el mismo punto.
        'distancia_punto_repetido_m' => 30,

        // Distancia máxima (m) entre el punto del técnico y el del auditor para considerarlos el mismo sitio.
        'distancia_auditor_max_m' => 200,

        // Motivos por los que el auditor no pudo verificar la suspensión en sitio.
        'motivos_no_verificables' => ['no_ubico_direccion', 'predio_cerrado', 'usuario_impide', 'riesgo_sitio', 'redes_terraza'],

        // Límites de Colombia para descartar coordenadas imposibles.
        'limites' => ['lat_min' => -4.3, 'lat_max' => 13.5, 'lon_min' => -79.1, 'lon_max' => -66.8],
    ],

];
