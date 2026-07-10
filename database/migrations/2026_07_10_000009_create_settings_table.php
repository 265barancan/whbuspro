<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // .env'den gelen varsayılan değerleri veritabanına aktar (İlk kurulum kolaylığı)
        DB::table('settings')->insert([
            ['key' => 'whatsapp_api_url', 'value' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_api_version', 'value' => env('WHATSAPP_API_VERSION', 'v20.0'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_phone_number_id', 'value' => env('WHATSAPP_PHONE_NUMBER_ID'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_business_account_id', 'value' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_token', 'value' => env('WHATSAPP_TOKEN'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_app_secret', 'value' => env('WHATSAPP_APP_SECRET'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_verify_token', 'value' => env('WHATSAPP_VERIFY_TOKEN'), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
