<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CheckWhatsAppQualityRating;

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Meta Kalite Puanını saatlik olarak kontrol et
Schedule::job(new CheckWhatsAppQualityRating)->hourly();

