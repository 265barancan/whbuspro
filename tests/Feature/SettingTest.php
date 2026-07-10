<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Can Baran',
            'email' => 'admin@whbuspro.com',
            'password' => 'admin12345',
            'role' => 'admin'
        ]);

        $this->token = $this->admin->createToken('auth_token')->plainTextToken;
    }

    /**
     * Test getting settings returns seeded defaults.
     */
    public function test_get_settings_returns_correct_structure(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}"
        ])->getJson('/api/settings/whatsapp');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'whatsapp_api_url',
            'whatsapp_api_version',
            'whatsapp_phone_number_id',
            'whatsapp_business_account_id',
            'whatsapp_token',
            'whatsapp_app_secret',
            'whatsapp_verify_token'
        ]);
    }

    /**
     * Test saving settings updates the database and dynamic config overrides.
     */
    public function test_save_settings_updates_db_and_overrides_configs(): void
    {
        $payload = [
            'whatsapp_api_url' => 'https://new-graph.facebook.com',
            'whatsapp_api_version' => 'v21.0',
            'whatsapp_phone_number_id' => 'new_phone_id',
            'whatsapp_business_account_id' => 'new_waba_id',
            'whatsapp_token' => 'new_token',
            'whatsapp_app_secret' => 'new_secret',
            'whatsapp_verify_token' => 'new_verify',
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}"
        ])->postJson('/api/settings/whatsapp', $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'WhatsApp ayarları başarıyla güncellendi.']);

        // Veritabanının güncellendiğini kontrol et
        $this->assertEquals('new_phone_id', Setting::get('whatsapp_phone_number_id'));
        $this->assertEquals('new_waba_id', Setting::get('whatsapp_business_account_id'));

        // Dinamik olarak config'lerin güncellendiğini doğrula
        // Not: AppServiceProvider boot() metodunda config() yazıldığı için, bu HTTP isteğinden sonraki PHP işleyişlerinde yeni değerler etkin olur.
        // Biz de manuel olarak AppServiceProvider boot tetikleyebiliriz ya da yeni bir request atıp test edebiliriz:
        
        $this->withHeaders([
            'Authorization' => "Bearer {$this->token}"
        ])->getJson('/api/settings/whatsapp'); // provider bootstrapped again in request cycle
        
        $this->assertEquals('new_phone_id', config('services.whatsapp.phone_number_id'));
        $this->assertEquals('new_waba_id', config('services.whatsapp.business_account_id'));
    }
}
