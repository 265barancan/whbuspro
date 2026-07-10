<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\TemplateController;

// Genel Durum Endpoint'i (Korumasız)
Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String()
    ]);
});

// Misafir / Login Rotaları
Route::post('/auth/login', [AuthController::class, 'login']);

// Kimlik Doğrulamalı API Rotaları (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth İşlemleri
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Şablon (Templates) Rotaları
    Route::post('/templates/sync', [TemplateController::class, 'sync']);
    Route::get('/templates', [TemplateController::class, 'index']);

    // Kişiler (Contacts) Rotaları
    Route::post('/contacts/import', [ContactController::class, 'import']);
    Route::apiResource('contacts', ContactController::class);

    // Kişi Listeleri (Contact Lists) Rotaları
    Route::post('/contact-lists/{contact_list}/attach', [ContactListController::class, 'attachContacts']);
    Route::post('/contact-lists/{contact_list}/detach', [ContactListController::class, 'detachContacts']);
    Route::apiResource('contact-lists', ContactListController::class);

    // Kampanyalar (Campaigns) Rotaları
    Route::get('/campaigns/{campaign}/errors', [CampaignController::class, 'errors']);
    Route::post('/campaigns/{campaign}/trigger', [CampaignController::class, 'trigger']);
    Route::apiResource('campaigns', CampaignController::class);

    // Ayarlar (Settings) Rotaları
    Route::get('/settings/whatsapp', [\App\Http\Controllers\SettingController::class, 'getWhatsAppSettings']);
    Route::post('/settings/whatsapp', [\App\Http\Controllers\SettingController::class, 'updateWhatsAppSettings']);

});
