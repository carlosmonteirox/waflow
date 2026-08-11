<?php

use App\Services\Billing\InterRenewalService;
use App\Services\PlatformMaintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(PlatformMaintenance::class)->expireTemporaryRecords();
})->everyFiveMinutes()->name('waflow-expire-temporary-records')->withoutOverlapping();

Schedule::call(function (): void {
    app(InterRenewalService::class)->generateDueCharges();
})->hourly()->name('waflow-inter-renewals')->withoutOverlapping();
