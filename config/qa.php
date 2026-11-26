<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QA Time Tools
    |--------------------------------------------------------------------------
    |
    | Alat bantu QA untuk menggeser waktu aplikasi (uji lead time purchasing).
    | Hanya aktif di environment non-production dan bila flag ini true.
    |
    */

    'time_tools' => (bool) env('QA_TIME_TOOLS', false),

    'clock_path' => env('QA_CLOCK_PATH', storage_path('framework/qa-clock.json')),

];
