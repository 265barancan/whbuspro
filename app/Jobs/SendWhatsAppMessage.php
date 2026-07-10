<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;
use App\Services\WhatsAppClient;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $messageId;
    protected array $parameters;

    /**
     * Toplu mesaj gönderimi denetimlerini içeren Job sınıfı.
     *
     * @param int $messageId messages tablosundaki tekil mesaj ID'si
     * @param array $parameters Mesaj şablonundaki değişkenler
     */
    public function __construct(int $messageId, array $parameters = [])
    {
        $this->messageId = $messageId;
        $this->parameters = $parameters;
    }

    /**
     * Job'ın maksimum deneme sayısı (Retry).
     */
    public $tries = 3;

    /**
     * Yeniden deneme aralıkları (Exponential backoff).
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Job'ı çalıştırır.
     */
    public function handle(WhatsAppClient $client): void
    {
        $message = Message::with(['contact', 'campaign.template'])->find($this->messageId);

        if (!$message) {
            Log::warning("Queue Job Warning: Message ID {$this->messageId} not found in database. Job skipped.");
            return;
        }

        // Gönderilmiş veya iptal edilmiş mesajları atla
        if (in_array($message->status, ['sent', 'delivered', 'read'])) {
            return;
        }

        $contact = $message->contact;
        $campaign = $message->campaign;
        $template = $campaign->template;

        // 0. Kampanya Durum Kontrolü
        // Kampanya durdurulduysa (paused) veya başarısız (failed) ise gönderimi iptal et.
        if (in_array($campaign->status, ['paused', 'failed'])) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Kampanya durdurulduğu (paused/failed) için gönderim yapılmadı.'
            ]);
            Log::info("Message skipped: Campaign #{$campaign->id} is paused or failed.");
            return;
        }

        // 1. Opt-in ve Blok Kontrolleri (Spam Önleme)
        if (!$contact->opted_in || $contact->status === 'blocked') {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Alıcı izin vermedi (opt-in = false) veya sistemde engelli.'
            ]);
            Log::info("Message skipped: Contact is blocked or has not opted-in.", [
                'contact_id' => $contact->id,
                'phone' => $contact->phone_number
            ]);
            return;
        }

        // 2. Throttling (Redis Hız Sınırlaması)
        // Kampanyadaki throttle_per_minute değerine göre dakikalık gönderim sınırı uygulanır.
        $throttleKey = "campaign_throttle:{$campaign->id}";
        $limit = $campaign->throttle_per_minute > 0 ? $campaign->throttle_per_minute : 60;

        Redis::throttle($throttleKey)
            ->allow($limit)
            ->every(60)
            ->block(10) // 10 saniye boyunca yer açılmasını bekle, açılmazsa release et
            ->then(
                function () use ($client, $message, $contact, $template, $campaign) {
                    try {
                        // İşleme başlamadan önce tekrar kampanya durumunu kontrol et (race condition koruması)
                        if ($campaign->fresh()->status === 'paused') {
                            $message->update([
                                'status' => 'failed',
                                'error_message' => 'Gönderim öncesi kampanya durdurulduğu tespit edildi.'
                            ]);
                            return;
                        }

                        // Jitter ekleme (Bot tespiti ve spama düşmeyi engellemek için küçük rastgele bekleme)
                        usleep(rand(100000, 500000)); // 100ms ile 500ms arası bekle

                        $response = $client->sendTemplateMessage(
                            $contact->phone_number,
                            $template->meta_template_name,
                            $template->language_code,
                            $this->parameters
                        );

                        $waMessageId = $response['messages'][0]['id'] ?? null;

                        $message->update([
                            'status' => 'sent',
                            'wa_message_id' => $waMessageId,
                            'sent_at' => now(),
                            'error_message' => null
                        ]);

                        Log::info("Message sent successfully via Queue Job.", [
                            'message_id' => $message->id,
                            'wa_message_id' => $waMessageId
                        ]);

                    } catch (\Exception $e) {
                        $message->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage()
                        ]);

                        // Circuit Breaker denetimini çalıştır
                        $this->checkCircuitBreaker($campaign);

                        throw $e; // Yeniden denemesi için hatayı fırlat
                    }
                },
                function () {
                    // Dakikalık sınır aşıldıysa, işi 10 saniye erteleyerek kuyruğa geri koy (Release)
                    Log::debug("Campaign rate limit reached. Re-queueing job.", ['campaign_id' => $this->messageId]);
                    return $this->release(10);
                }
            );
    }

    /**
     * Hata oranını hesaplayıp eşik aşılırsa kampanyayı duraklatır (Circuit Breaker).
     */
    protected function checkCircuitBreaker($campaign): void
    {
        // Gönderimi yapılmış veya başarısız olmuş tüm mesajları say
        $totalProcessed = Message::where('campaign_id', $campaign->id)
            ->whereIn('status', ['sent', 'delivered', 'read', 'failed'])
            ->count();

        $totalFailed = Message::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();

        // En az 20 mesaj gönderildiğinde devreye gir
        if ($totalProcessed >= 20) {
            $failureRate = ($totalFailed / $totalProcessed) * 100;

            // Hata oranı %10 veya üzerindeyse kampanyayı otomatik duraklat
            if ($failureRate >= 10.0) {
                $campaign->update(['status' => 'paused']);
                Log::warning("Circuit Breaker Aktifleşti: Kampanya #{$campaign->id} yüksek hata oranı (%{$failureRate}) nedeniyle durduruldu. Toplam İşlenen: {$totalProcessed}, Başarısız: {$totalFailed}");
            }
        }
    }
}
