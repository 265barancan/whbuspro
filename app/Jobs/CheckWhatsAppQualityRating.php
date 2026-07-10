<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\WhatsAppClient;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;

class CheckWhatsAppQualityRating implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Meta API'den kalite puanı denetimi yapan periyodik arka plan işi.
     */
    public function handle(WhatsAppClient $client): void
    {
        try {
            $statusData = $client->fetchPhoneNumberStatus();

            $quality = $statusData['quality_rating'] ?? 'UNKNOWN';
            $status = $statusData['status'] ?? 'UNKNOWN';

            Log::info("WhatsApp account status checked.", [
                'quality_rating' => $quality,
                'status' => $status
            ]);

            // Güvenlik Kısıtlaması (Circuit Breaker - Hesap Düzeyi):
            // Kalite "RED" (Kırmızı) ise ya da numara flaglenmiş / kısıtlanmışsa, 
            // banlanmayı önlemek için devam eden tüm kampanyaları otomatik durdur.
            $shouldPause = ($quality === 'RED') || 
                           in_array($status, ['FLAGGED', 'RESTRICTED', 'BLOCKED']);

            if ($shouldPause) {
                // Aktif gönderimde olan veya sıradaki kampanyaları duraklat
                $pausedCount = Campaign::whereIn('status', ['draft', 'queued', 'sending'])
                    ->update(['status' => 'paused']);

                Log::warning("WHATSAPP HESAP DURUMU KRİTİK! Aktif kampanyalar durduruldu.", [
                    'quality_rating' => $quality,
                    'status' => $status,
                    'paused_campaigns_count' => $pausedCount
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Quality Rating kontrolü başarısız oldu: " . $e->getMessage());
        }
    }
}
