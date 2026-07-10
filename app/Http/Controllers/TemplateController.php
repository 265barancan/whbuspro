<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Template;
use App\Services\WhatsAppClient;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    protected WhatsAppClient $whatsappClient;

    public function __construct(WhatsAppClient $whatsappClient)
    {
        $this->whatsappClient = $whatsappClient;
    }

    /**
     * Yerel veritabanındaki şablonları listeler.
     */
    public function index()
    {
        $templates = Template::latest()->get();
        return response()->json($templates);
    }

    /**
     * Meta API ile yerel şablonları senkronize eder (Değişken sayılarını hesaplar).
     */
    public function sync()
    {
        try {
            $metaTemplates = $this->whatsappClient->fetchTemplates();

            $syncedCount = 0;

            foreach ($metaTemplates as $metaTpl) {
                $name = $metaTpl['name'] ?? null;
                $language = $metaTpl['language'] ?? 'tr';
                $category = $metaTpl['category'] ?? 'UTILITY';
                $status = $metaTpl['status'] ?? 'APPROVED';
                $components = $metaTpl['components'] ?? [];

                if (!$name) {
                    continue;
                }

                // Gövde (BODY) bileşenindeki değişkenleri say (örn: {{1}}, {{2}})
                $variablesCount = 0;
                foreach ($components as $component) {
                    if (isset($component['type']) && strtoupper($component['type']) === 'BODY' && isset($component['text'])) {
                        preg_match_all('/{{\d+}}/', $component['text'], $matches);
                        if (!empty($matches[0])) {
                            // Benzersiz değişken adlarını say
                            $variablesCount = count(array_unique($matches[0]));
                        }
                    }
                }

                // Yerel veritabanını güncelle veya ekle
                Template::updateOrCreate(
                    [
                        'meta_template_name' => $name,
                        'language_code' => $language,
                    ],
                    [
                        'category' => $category,
                        'status' => $status,
                        'body_variables_count' => $variablesCount,
                    ]
                );

                $syncedCount++;
            }

            Log::info("Templates successfully synchronized with Meta API.", ['count' => $syncedCount]);

            return response()->json([
                'message' => 'Şablonlar başarıyla senkronize edildi.',
                'synced_count' => $syncedCount,
                'templates' => Template::latest()->get()
            ]);

        } catch (\Exception $e) {
            Log::error("Template senkronizasyon hatası: " . $e->getMessage());
            return response()->json([
                'message' => 'Şablon senkronizasyonu başarısız oldu.',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
