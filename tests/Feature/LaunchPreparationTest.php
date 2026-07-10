<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Template;
use App\Models\Campaign;
use App\Models\Message;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LaunchPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;
    protected Message $message;
    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.api_url' => 'https://graph.facebook.com',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.token' => 'mock_token',
        ]);

        // Redis throttling mock'ları
        Redis::shouldReceive('throttle')->andReturnSelf();
        Redis::shouldReceive('allow')->andReturnSelf();
        Redis::shouldReceive('every')->andReturnSelf();
        Redis::shouldReceive('block')->andReturnSelf();
        Redis::shouldReceive('then')->andReturnUsing(function ($callback) {
            return $callback();
        });

        // Test verileri
        $list = ContactList::create(['name' => 'Launch Test List']);
        $template = Template::create([
            'meta_template_name' => 'hello',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Resilience Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'throttle_per_minute' => 60,
            'status' => 'sending'
        ]);

        $this->contact = Contact::create([
            'phone_number' => '+905551112233',
            'opted_in' => true,
            'status' => 'active'
        ]);

        $this->message = Message::create([
            'campaign_id' => $this->campaign->id,
            'contact_id' => $this->contact->id,
            'status' => 'queued'
        ]);
    }

    /**
     * Test queue job handles temporary Meta HTTP 429 (Rate Limit) error by throwing exception (triggering retry).
     */
    public function test_job_handles_meta_rate_limit_error_triggering_retry(): void
    {
        // Meta API'nin HTTP 429 hata döndüğünü taklit et
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded.',
                    'type' => 'OAuthException',
                    'code' => 4
                ]
            ], 429)
        ]);

        $job = new SendWhatsAppMessage($this->message->id, ['Param']);

        $exceptionThrown = false;
        try {
            $job->handle(new \App\Services\WhatsAppClient());
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('Rate limit exceeded', $e->getMessage());
        }

        // Job hata fırlatmalı ki Laravel Queue otomatik olarak retry (yeniden deneme) mekanizmasını tetiklesin
        $this->assertTrue($exceptionThrown);

        // Veritabanındaki mesaj durumunun 'failed' yapıldığını ve hata logunun kaydedildiğini kontrol et
        $this->assertDatabaseHas('messages', [
            'id' => $this->message->id,
            'status' => 'failed',
        ]);
        
        $this->assertNotNull($this->message->refresh()->error_message);
    }

    /**
     * Test queue job handles Meta HTTP 500 (Server Error) by throwing exception (triggering retry).
     */
    public function test_job_handles_meta_server_error_triggering_retry(): void
    {
        // Meta API'nin HTTP 500 hata döndüğünü taklit et
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'error' => [
                    'message' => 'Temporary server error.',
                    'type' => 'OAuthException',
                    'code' => 2
                ]
            ], 500)
        ]);

        $job = new SendWhatsAppMessage($this->message->id, ['Param']);

        $exceptionThrown = false;
        try {
            $job->handle(new \App\Services\WhatsAppClient());
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('Temporary server error', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown);
    }
}
