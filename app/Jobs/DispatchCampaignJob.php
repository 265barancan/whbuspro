<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Campaign;
use App\Models\Message;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $campaignId;
    protected array $parameters;

    /**
     * Büyük alıcı listelerini arka planda parça parça (chunk) okuyup
     * gönderim kuyruğuna (SendWhatsAppMessage) ekleyen Job.
     *
     * @param int $campaignId Kampanya ID'si
     * @param array $parameters Şablon parametreleri
     */
    public function __construct(int $campaignId, array $parameters = [])
    {
        $this->campaignId = $campaignId;
        $this->parameters = $parameters;
    }

    /**
     * Job'ı çalıştırır.
     */
    public function handle(): void
    {
        $campaign = Campaign::with('list')->find($this->campaignId);

        if (!$campaign) {
            Log::warning("DispatchCampaignJob Warning: Campaign ID {$this->campaignId} not found.");
            return;
        }

        // Durum kontrolü (Sadece queued veya draft durumundakiler gönderilebilir)
        if (!in_array($campaign->status, ['draft', 'queued', 'sending'])) {
            return;
        }

        $campaign->update(['status' => 'sending']);

        Log::info("Campaign dispatching started.", ['campaign_id' => $campaign->id]);

        // Listeye ait aktif ve opt-in olan kişileri chunk (parçalı) olarak çek
        // Bu sayede RAM aşımı ve zaman aşımı (timeout) engellenmiş olur.
        $campaign->list->contacts()
            ->where('opted_in', true)
            ->where('status', 'active')
            ->chunk(500, function ($contacts) use ($campaign) {
                DB::transaction(function () use ($contacts, $campaign) {
                    foreach ($contacts as $contact) {
                        // 1. messages tablosuna queued kaydı aç
                        $message = Message::create([
                            'campaign_id' => $campaign->id,
                            'contact_id' => $contact->id,
                            'status' => 'queued',
                        ]);

                        // 2. Her bir alıcı için tekil gönderim işini kuyruğa (Redis) gönder
                        // parameters dizisini gönderiyoruz (İleride kişiye özel dinamik değişkenler de eklenebilir)
                        SendWhatsAppMessage::dispatch($message->id, $this->parameters);
                    }
                });
            });

        // Tüm kayıtlar başarıyla kuyruğa eklendikten sonra durumu completed yapalım
        // Not: completed gönderimlerin bittiğini değil, kuyruğa atılma işleminin bittiğini ifade eder.
        $campaign->update(['status' => 'completed']);

        Log::info("Campaign dispatching completed.", ['campaign_id' => $campaign->id]);
    }
}
