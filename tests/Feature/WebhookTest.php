<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Campaign;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test credentials set et
        config([
            'services.whatsapp.verify_token' => 'test_verify_token',
            'services.whatsapp.app_secret' => 'test_app_secret',
        ]);
    }

    /**
     * Webhook GET verification success test.
     */
    public function test_webhook_verification_success(): void
    {
        $response = $this->getJson('/webhook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token',
            'hub_challenge' => '123456789'
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('123456789');
    }

    /**
     * Webhook GET verification forbidden test.
     */
    public function test_webhook_verification_forbidden(): void
    {
        $response = $this->getJson('/webhook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'invalid_token',
            'hub_challenge' => '123456789'
        ]));

        $response->assertStatus(403);
    }

    /**
     * Webhook POST signature validation success test.
     */
    public function test_webhook_post_signature_validation_success(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test_app_secret');

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature
        ])->post('/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'received']);
    }

    /**
     * Webhook POST signature validation failure test.
     */
    public function test_webhook_post_signature_validation_failure(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $signature = 'sha256=invalid_signature';

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature
        ])->post('/webhook', $payload);

        $response->assertStatus(401);
    }

    /**
     * Webhook POST updates message status to delivered in database.
     */
    public function test_webhook_updates_message_status_to_delivered(): void
    {
        // Gerekli verileri Mock et
        $contact = Contact::create([
            'phone_number' => '+905554443322',
            'opted_in' => true,
            'status' => 'active'
        ]);

        $template = Template::create([
            'meta_template_name' => 'hello_world',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $campaign = Campaign::create([
            'name' => 'Test Campaign',
            'template_id' => $template->id,
            'list_id' => 1, // list_id pivot/iliskisi
            'status' => 'sending'
        ]);

        $message = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'wa_message_id' => 'wamid.HBgLOTA1NTU0NDQzMzIyFQIAERg2REUzRjQ1NkE3Qjg5OAA=',
            'status' => 'sent'
        ]);

        // Webhook Payload'u
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgLOTA1NTU0NDQzMzIyFQIAERg2REUzRjQ1NkE3Qjg5OAA=',
                                        'status' => 'delivered',
                                        'timestamp' => 1719920400,
                                        'recipient_id' => '905554443322'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test_app_secret');

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature
        ])->post('/webhook', $payload);

        $response->assertStatus(200);

        // Veritabanını kontrol et
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'status' => 'delivered',
            'delivered_at' => '2024-07-02 11:40:00' // 1719920400 Unix Timestamp
        ]);
    }

    /**
     * Webhook POST blocks contact on inbound "STOP" keyword.
     */
    public function test_webhook_blocks_contact_on_inbound_stop(): void
    {
        $contact = Contact::create([
            'phone_number' => '+905554443322',
            'opted_in' => true,
            'status' => 'active'
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'messages' => [
                                    [
                                        'from' => '905554443322',
                                        'id' => 'wamid.HBgLOTA1NTU0NDQzMzIyFQIAEhgWM0EBQ0QxMkUzNDU2NzhGOUFCQ0Q=',
                                        'timestamp' => 1719920400,
                                        'text' => [
                                            'body' => 'STOP'
                                        ],
                                        'type' => 'text'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test_app_secret');

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature
        ])->post('/webhook', $payload);

        $response->assertStatus(200);

        // Veritabanını kontrol et: Opted-in false olmalı, status blocked olmalı
        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'opted_in' => false,
            'status' => 'blocked'
        ]);

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
            'opted_out_at' => null
        ]);
    }
}
