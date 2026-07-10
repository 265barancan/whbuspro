<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Template;
use App\Models\Campaign;
use App\Models\Message;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SimulateThrottling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:simulate-throttling 
                            {--limit=10 : Dakikada gönderilecek maksimum mesaj sayısı} 
                            {--count=20 : Gönderilecek simüle mesaj adeti}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toplu gönderimlerdeki hız sınırlama (Throttling) mekanizmasını yerel olarak simüle eder ve ölçer.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $count = (int) $this->option('count');

        $this->info("=== WhatsApp Throttling Simülasyonu Başlatılıyor ===");
        $this->info("Hedef Hız Sınırı: Dakikada {$limit} mesaj");
        $this->info("Toplam Mesaj: {$count} adet");

        // 1. Meta API HTTP çağrılarını sahte olarak başarılı dön
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.simulated_' . rand(1000, 9999)]]
            ], 200)
        ]);

        // 2. Simülasyon Verilerini Hazırla (Temizle ve Oluştur)
        $this->comment("Veritabanı verileri hazırlanıyor...");
        
        $template = Template::firstOrCreate(
            ['meta_template_name' => 'sim_template'],
            ['language_code' => 'tr', 'status' => 'APPROVED', 'body_variables_count' => 1]
        );

        $list = ContactList::create(['name' => 'Simülasyon Listesi']);

        $campaign = Campaign::create([
            'name' => 'Simülasyon Kampanyası',
            'template_id' => $template->id,
            'list_id' => $list->id,
            'throttle_per_minute' => $limit,
            'status' => 'sending'
        ]);

        // Alıcıları oluştur
        $contactIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $contact = Contact::create([
                'phone_number' => '+90555999' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'full_name' => 'Simüle Alıcı ' . $i,
                'opted_in' => true,
                'status' => 'active'
            ]);
            $contactIds[] = $contact->id;
        }
        $list->contacts()->attach($contactIds);

        // Mesajları queued olarak aç ve job'ları kuyruğa yolla
        $this->comment("Mesajlar kuyruğa yollanıyor...");
        $messageIds = [];
        foreach ($contactIds as $contactId) {
            $message = Message::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'status' => 'queued'
            ]);
            $messageIds[] = $message->id;
            
            // Kuyruğa ekle
            SendWhatsAppMessage::dispatch($message->id, ['Değişken']);
        }

        $this->info("Tüm işler kuyruğa yollandı!");
        
        // Eğer queue connection 'sync' ise doğrudan bitmiştir
        if (config('queue.default') === 'sync') {
            $this->warn("Not: QUEUE_CONNECTION=sync ayarlı olduğu için gönderimler senkron (anlık) yapıldı.");
            $this->printSummary($campaign->id);
            return 0;
        }

        $this->info("Simülasyon başladı. Lütfen başka bir terminalde kuyruk işçisini çalıştırın:");
        $this->warn("👉 php artisan queue:work --sleep=1 --tries=1");
        $this->info("İlerlemeyi canlı izlemek için bu pencereyi açık tutun...\n");

        // Canlı izleme ekranı
        $startTime = microtime(true);
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        while (true) {
            $processedCount = Message::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'failed'])
                ->count();

            $bar->setProgress($processedCount);

            if ($processedCount >= $count) {
                break;
            }

            sleep(1);
        }

        $bar->finish();
        $this->info("\n\nSimülasyon tamamlandı!");

        $this->printSummary($campaign->id);

        return 0;
    }

    /**
     * Gönderim oranlarını özetler.
     */
    protected function printSummary(int $campaignId): void
    {
        $messages = Message::where('campaign_id', $campaignId)->get();
        
        $total = $messages->count();
        $sent = $messages->where('status', 'sent')->count();
        $failed = $messages->where('status', 'failed')->count();

        $sentTimes = $messages->whereNotNull('sent_at')->pluck('sent_at')->map(fn($t) => strtotime($t))->toArray();
        
        if (count($sentTimes) > 1) {
            $duration = max($sentTimes) - min($sentTimes);
            $duration = $duration > 0 ? $duration : 1; // Sıfıra bölünme önleme
            $ratePerMinute = round(($sent / $duration) * 60, 2);
        } else {
            $duration = 0;
            $ratePerMinute = 0;
        }

        $this->line("");
        $this->info("=== GÖNDERİM ANALİZİ ===");
        $this->line("Başarılı Mesaj  : {$sent} adet");
        $this->line("Başarısız Mesaj : {$failed} adet");
        $this->line("Toplam Süre     : {$duration} saniye");
        $this->warn("Gerçekleşen Hız : Dakikada {$ratePerMinute} mesaj (Hedef: " . ($ratePerMinute > 0 ? $ratePerMinute : 'N/A') . ")");
        $this->line("========================");
    }
}
