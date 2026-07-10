<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.api_url' => 'https://graph.facebook.com',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.business_account_id' => '0987654321',
            'services.whatsapp.token' => 'mock_token',
        ]);

        $this->admin = User::create([
            'name' => 'Can Baran',
            'email' => 'admin@whbuspro.com',
            'password' => 'admin12345',
            'role' => 'admin'
        ]);

        $this->token = $this->admin->createToken('auth_token')->plainTextToken;
    }

    /**
     * Test template synchronization and variables calculation.
     */
    public function test_template_sync_calculates_variables_correctly(): void
    {
        // Meta API şablon yanıtını taklit et
        Http::fake([
            'https://graph.facebook.com/v20.0/0987654321/message_templates' => Http::response([
                'data' => [
                    [
                        'name' => 'welcome_campaign',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'tr',
                        'components' => [
                            [
                                'type' => 'HEADER',
                                'format' => 'TEXT',
                                'text' => 'Hoş Geldiniz'
                            ],
                            [
                                'type' => 'BODY',
                                'text' => 'Merhaba {{1}}, sistemdeki kargo takip numaranız: {{2}}. Bizi seçtiğiniz için teşekkürler.'
                            ]
                        ]
                    ],
                    [
                        'name' => 'no_vars_template',
                        'status' => 'APPROVED',
                        'category' => 'MARKETING',
                        'language' => 'en',
                        'components' => [
                            [
                                'type' => 'BODY',
                                'text' => 'No variables here, static template.'
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}"
        ])->postJson('/api/templates/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Şablonlar başarıyla senkronize edildi.',
            'synced_count' => 2
        ]);

        // Veritabanını kontrol et: Değişken sayıları doğru hesaplanmış olmalı
        $this->assertDatabaseHas('templates', [
            'meta_template_name' => 'welcome_campaign',
            'language_code' => 'tr',
            'category' => 'UTILITY',
            'body_variables_count' => 2 // {{1}} ve {{2}}
        ]);

        $this->assertDatabaseHas('templates', [
            'meta_template_name' => 'no_vars_template',
            'language_code' => 'en',
            'body_variables_count' => 0
        ]);
    }
}
