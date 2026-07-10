<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Template;
use App\Models\Campaign;
use App\Models\Message;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.api_url' => 'https://graph.facebook.com',
            'services.whatsapp.api_version' => 'v20.0',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.token' => 'mock_token',
        ]);
    }

    /**
     * Test campaign creation validation.
     */
    public function test_campaign_store_validation(): void
    {
        $response = $this->postJson('/api/campaigns', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'template_id', 'list_id']);
    }

    /**
     * Test campaign trigger dispatches DispatchCampaignJob.
     */
    public function test_campaign_trigger_dispatches_dispatcher_job(): void
    {
        Queue::fake();

        $list = ContactList::create(['name' => 'Newsletter List']);
        $template = Template::create([
            'meta_template_name' => 'newsletter_tpl',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $campaign = Campaign::create([
            'name' => 'Promo Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'throttle_per_minute' => 30
        ]);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/trigger", [
            'parameters' => ['Yaz Kampanyası']
        ]);

        $response->assertStatus(200);
        $this->assertEquals('queued', $campaign->refresh()->status);

        // DispatchCampaignJob kuyruğa eklendi mi?
        Queue::assertPushed(DispatchCampaignJob::class, function ($job) use ($campaign) {
            return $job->campaignId === $campaign->id;
        });
    }

    /**
     * Test DispatchCampaignJob creates messages and dispatches SendWhatsAppMessage jobs.
     */
    public function test_dispatcher_job_creates_messages_and_dispatches_sends(): void
    {
        Queue::fake();

        $list = ContactList::create(['name' => 'A Grubu']);
        $template = Template::create([
            'meta_template_name' => 'welcome_tpl',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $campaign = Campaign::create([
            'name' => 'Welcome Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'status' => 'queued'
        ]);

        // 3 Alıcı ekle (2 opt-in, 1 opted-out)
        $c1 = Contact::create(['phone_number' => '+905551111111', 'opted_in' => true, 'status' => 'active']);
        $c2 = Contact::create(['phone_number' => '+905552222222', 'opted_in' => true, 'status' => 'active']);
        $c3 = Contact::create(['phone_number' => '+905553333333', 'opted_in' => false, 'status' => 'blocked']);

        $list->contacts()->attach([$c1->id, $c2->id, $c3->id]);

        // Dispatcher işini elle tetikle
        $dispatcher = new DispatchCampaignJob($campaign->id, ['Ahmet']);
        $dispatcher->handle();

        // 1. messages tablosuna sadece 2 opt-in üye için queued kaydı açılmalı
        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'contact_id' => $c1->id,
            'status' => 'queued'
        ]);
        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'contact_id' => $c2->id,
            'status' => 'queued'
        ]);
        $this->assertDatabaseMissing('messages', [
            'campaign_id' => $campaign->id,
            'contact_id' => $c3->id
        ]);

        // 2. SendWhatsAppMessage job'ları kuyruğa itildi mi?
        Queue::assertPushed(SendWhatsAppMessage::class, 2);

        // Kampanya durumu completed olmalı (kuyruğa ekleme tamamlandı)
        $this->assertEquals('completed', $campaign->refresh()->status);
    }

    /**
     * Test SendWhatsAppMessage job execution.
     */
    public function test_send_whatsapp_message_job_executes_successfully(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.test_message_id_123']]
            ], 200)
        ]);

        // Redis throttle metodunu fake et (Laravel Redis Mock/Connection default)
        Redis::shouldReceive('throttle')
            ->once()
            ->andReturnSelf();
        
        Redis::shouldReceive('allow')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('every')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('block')
            ->once()
            ->andReturnSelf();

        // Then metoduna callback parametresini paslayıp çalıştır
        Redis::shouldReceive('then')
            ->once()
            ->andReturnUsing(function ($callback, $failure) {
                return $callback(); // Gönderim callback'ini çalıştır
            });

        $list = ContactList::create(['name' => 'B Grubu']);
        $template = Template::create([
            'meta_template_name' => 'hello_world',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $campaign = Campaign::create([
            'name' => 'Redis Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'throttle_per_minute' => 60
        ]);

        $contact = Contact::create(['phone_number' => '+905551111111', 'opted_in' => true, 'status' => 'active']);
        
        $message = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued'
        ]);

        // Job'ı çalıştır
        $job = new SendWhatsAppMessage($message->id, ['Param1']);
        $job->handle(new \App\Services\WhatsAppClient());

        // Mesaj durumu 'sent' olarak güncellenmeli ve wa_message_id yazılmalı
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'status' => 'sent',
            'wa_message_id' => 'wamid.test_message_id_123'
        ]);
    }

    /**
     * Test Circuit Breaker pauses campaign if failure rate >= 10% on at least 20 messages.
     */
    public function test_campaign_circuit_breaker_pauses_campaign_on_high_failure_rate(): void
    {
        // Meta API'nin hata döneceğini simüle et
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'error' => ['message' => 'Rate limit exceeded']
            ], 429)
        ]);

        Redis::shouldReceive('throttle')->andReturnSelf();
        Redis::shouldReceive('allow')->andReturnSelf();
        Redis::shouldReceive('every')->andReturnSelf();
        Redis::shouldReceive('block')->andReturnSelf();
        Redis::shouldReceive('then')->andReturnUsing(function ($callback) {
            return $callback();
        });

        $list = ContactList::create(['name' => 'C Grubu']);
        $template = Template::create([
            'meta_template_name' => 'hello_world',
            'language_code' => 'tr',
            'status' => 'APPROVED'
        ]);

        $campaign = Campaign::create([
            'name' => 'Circuit Breaker Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'throttle_per_minute' => 60,
            'status' => 'sending'
        ]);

        // 17 başarılı, 2 başarısız mesaj hazırla (Toplam 19)
        for ($i = 1; $i <= 17; $i++) {
            $contact = Contact::create(['phone_number' => "+9055500000{$i}", 'opted_in' => true]);
            Message::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'status' => 'sent'
            ]);
        }
        for ($i = 18; $i <= 19; $i++) {
            $contact = Contact::create(['phone_number' => "+9055500000{$i}", 'opted_in' => true]);
            Message::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'status' => 'failed',
                'error_message' => 'Failed call'
            ]);
        }

        // 20. mesajı (sıradaki) oluştur
        $targetContact = Contact::create(['phone_number' => '+905559999999', 'opted_in' => true]);
        $message = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $targetContact->id,
            'status' => 'queued'
        ]);

        // Job'ı çalıştır (bu job başarısız olacak ve 3. hatayı ekleyecek -> 3/20 = %15 hata oranı)
        try {
            $job = new SendWhatsAppMessage($message->id, []);
            $job->handle(new \App\Services\WhatsAppClient());
        } catch (\Exception $e) {
            // Hatayı yut
        }

        // Kampanyanın otomatik olarak paused (duraklatıldı) durumuna geçmesini bekle
        $this->assertEquals('paused', $campaign->refresh()->status);
    }

    /**
     * Test periyodik kalite denetim job'ının olumsuz durumlarda kampanyaları durdurması.
     */
    public function test_check_whatsapp_quality_rating_pauses_campaigns_on_red_rating(): void
    {
        // Meta numara bilgisinin RED döneceğini simüle et
        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890' => Http::response([
                'quality_rating' => 'RED',
                'status' => 'CONNECTED'
            ], 200)
        ]);

        $list = ContactList::create(['name' => 'D Grubu']);
        $template = Template::create(['meta_template_name' => 'hello', 'status' => 'APPROVED']);
        
        $campaign = Campaign::create([
            'name' => 'Active Campaign',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'status' => 'sending'
        ]);

        // Kalite kontrol job'ını çalıştır
        $job = new \App\Jobs\CheckWhatsAppQualityRating();
        $job->handle(new \App\Services\WhatsAppClient());

        // Kalite Kırmızı olduğu için kampanyanın otomatik durdurulduğunu doğrula
        $this->assertEquals('paused', $campaign->refresh()->status);
    }
}

