<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Settings tablosu varsa verileri dinamik olarak config üzerine yaz
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                config([
                    'services.whatsapp.api_url' => \App\Models\Setting::get('whatsapp_api_url', config('services.whatsapp.api_url')),
                    'services.whatsapp.api_version' => \App\Models\Setting::get('whatsapp_api_version', config('services.whatsapp.api_version')),
                    'services.whatsapp.phone_number_id' => \App\Models\Setting::get('whatsapp_phone_number_id', config('services.whatsapp.phone_number_id')),
                    'services.whatsapp.business_account_id' => \App\Models\Setting::get('whatsapp_business_account_id', config('services.whatsapp.business_account_id')),
                    'services.whatsapp.token' => \App\Models\Setting::get('whatsapp_token', config('services.whatsapp.token')),
                    'services.whatsapp.app_secret' => \App\Models\Setting::get('whatsapp_app_secret', config('services.whatsapp.app_secret')),
                    'services.whatsapp.verify_token' => \App\Models\Setting::get('whatsapp_verify_token', config('services.whatsapp.verify_token')),
                ]);
            }
        } catch (\Exception $e) {
            // Veritabanı bağlantısı henüz yoksa (örn: migration'dan önce) yut
        }
    }
}
