<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QA Time Tools
    |--------------------------------------------------------------------------
    |
    | Alat bantu QA untuk menggeser waktu aplikasi (uji lead time purchasing).
    | Hanya aktif bila APP_ENV=local DAN QA_LEAD_TIME_TEST=true (lihat QaClock::enabled()).
    |
    */

    'lead_time_test' => (bool) env('QA_LEAD_TIME_TEST', false),

    'clock_path' => env('QA_CLOCK_PATH', storage_path('framework/qa-clock.json')),

];
