<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\WhatsAppClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WhatsAppClientTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.api_url' => 'https://graph.facebook.com',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.business_account_id' => '0987654321',
            'services.whatsapp.token' => 'mock_token',
        ]);

        $this->client = new WhatsAppClient();
    }

    /**
     * Test successful template message send.
     */
    public function test_send_template_message_success(): void
    {
        // Meta HTTP isteğini taklit et
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => '905554443322', 'wa_id' => '905554443322']
                ],
                'messages' => [
                    ['id' => 'wamid.HBgLOTA1NTU0NDQzMzIyFQIAERg2REUzRjQ1NkE3Qjg5OAA=']
                ]
            ], 200)
        ]);

        $response = $this->client->sendTemplateMessage(
            '905554443322',
            'welcome_template',
            'tr',
            ['Ahmet', 'Kargo-123']
        );

        // API yanıt alanlarını kontrol et
        $this->assertArrayHasKey('messages', $response);
        $this->assertEquals('wamid.HBgLOTA1NTU0NDQzMzIyFQIAERg2REUzRjQ1NkE3Qjg5OAA=', $response['messages'][0]['id']);

        // Veritabanına istek logunun atılıp atılmadığını kontrol et
        $this->assertDatabaseHas('api_logs', [
            'endpoint' => 'POST /messages',
            'http_status' => 200,
        ]);

        // Meta'ya giden payload içeriğini denetle
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages' &&
                $data['to'] === '905554443322' &&
                $data['template']['name'] === 'welcome_template' &&
                $data['template']['components'][0]['parameters'][0]['text'] === 'Ahmet' &&
                $data['template']['components'][0]['parameters'][1]['text'] === 'Kargo-123';
        });
    }

    /**
     * Test template message send fails on Meta error.
     */
    public function test_send_template_message_failure(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190
                ]
            ], 401)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WhatsApp mesajı gönderilemedi: Invalid OAuth access token. (HTTP 401)');

        $this->client->sendTemplateMessage('905554443322', 'welcome_template', 'tr');

        // Hata durumunda da api_logs tablosuna kayıt atılmalı
        $this->assertDatabaseHas('api_logs', [
            'endpoint' => 'POST /messages (FAILED)',
            'http_status' => 500,
        ]);
    }

    /**
     * Test successful template list fetch.
     */
    public function test_fetch_templates_success(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/0987654321/message_templates' => Http::response([
                'data' => [
                    [
                        'name' => 'hello_world',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'tr'
                    ],
                    [
                        'name' => 'marketing_discount',
                        'status' => 'APPROVED',
                        'category' => 'MARKETING',
                        'language' => 'tr'
                    ]
                ]
            ], 200)
        ]);

        $templates = $this->client->fetchTemplates();

        $this->assertCount(2, $templates);
        $this->assertEquals('hello_world', $templates[0]['name']);
        $this->assertEquals('marketing_discount', $templates[1]['name']);

        $this->assertDatabaseHas('api_logs', [
            'endpoint' => 'GET /message_templates',
            'http_status' => 200,
        ]);
    }
}
