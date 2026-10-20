<?php

return [
    'connection' => 'sgd',
    'schema' => env('SGD_DB_SCHEMA', 'IDOSGD'),
    'tables' => [
        'TDTV_REMITOS',
        'TDTV_DESTINOS',
        'RHTM_DEPENDENCIA',
        'TDTR_REFERENCIA',
        'RHTM_PER_EMPLEADOS',
        'SI_MAE_TIPO_DOC',
        'TDTR_ESTADOS',
        'TDTX_DEPENDENCIA_EMPLEADO',
        'TDTX_REMITOS_RESUMEN',
    ],
];
