<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QA Time Tools
    |--------------------------------------------------------------------------
    |
    | Alat bantu QA untuk menggeser waktu aplikasi (uji lead time purchasing).
    | Hanya aktif bila APP_ENV=local (lihat QaClock::enabled()).
    |
    */

    'clock_path' => env('QA_CLOCK_PATH', storage_path('framework/qa-clock.json')),

];
