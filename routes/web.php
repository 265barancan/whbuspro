<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return response()->json([
        'message' => 'WhatsApp Business Bulk Messaging Platform API is running.'
    ]);
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

// Meta Webhook endpoints

Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'handle']);

