<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('purchasing:check-overdue', function (\App\Services\Purchasing\PurchasingPaymentService $paymentService) {
    $count = $paymentService->refreshAllOverdueStatuses();
    $this->info("Pemeriksaan selesai. {$count} termin/PO jatuh tempo telah diperiksa dan disinkronkan.");
})->purpose('Memeriksa dan memperbarui status jatuh tempo (overdue) untuk tagihan PO dan termin')
  ->daily();

