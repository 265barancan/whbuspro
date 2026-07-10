<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppClient
{
    protected string $apiUrl;
    protected string $apiVersion;
    protected string $phoneNumberId;
    protected string $businessAccountId;
    protected string $token;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com');
        $this->apiVersion = config('services.whatsapp.api_version', 'v20.0');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->businessAccountId = config('services.whatsapp.business_account_id');
        $this->token = config('services.whatsapp.token');
    }

    /**
     * Meta Cloud API üzerinden şablonlu mesaj gönderir.
     *
     * @param string $to Alıcı telefon numarası (E.164 formatında, örn: 905xxxxxxxxx)
     * @param string $templateName Meta'da onaylı şablon adı
     * @param string $languageCode Dil kodu (varsayılan: tr)
     * @param array $parameters Şablon gövdesindeki değişkenler (örn: ['Ahmet', 'Kargo No: 123'])
     * @return array API yanıtı
     * @throws \Exception
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'tr', array $parameters = []): array
    {
        $endpoint = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // Telefon numarasını temizle (sadece rakam kalsın, + işaretini kaldır)
        $cleanTo = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        // Değişkenleri ekle
        if (!empty($parameters)) {
            $formattedParams = [];
            foreach ($parameters as $param) {
                $formattedParams[] = [
                    'type' => 'text',
                    'text' => (string) $param,
                ];
            }
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $formattedParams,
                ]
            ];
        }

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->post($endpoint, $payload);

            $status = $response->status();
            $body = $response->json() ?? ['raw_body' => $response->body()];

            $this->logApiCall("POST /messages", $payload, $body, $status);

            if (!$response->successful()) {
                $errorMsg = $body['error']['message'] ?? 'Meta API hatası';
                throw new \Exception("WhatsApp mesajı gönderilemedi: {$errorMsg} (HTTP {$status})");
            }

            return $body;
        } catch (\Exception $e) {
            $this->logApiCall("POST /messages (FAILED)", $payload, ['error' => $e->getMessage()], 500);
            Log::error("WhatsAppClient Gönderim Hatası: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Meta Business Account düzeyindeki şablon listesini çeker.
     *
     * @return array Şablon listesi
     * @throws \Exception
     */
    public function fetchTemplates(): array
    {
        $endpoint = "{$this->apiUrl}/{$this->apiVersion}/{$this->businessAccountId}/message_templates";

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->get($endpoint);

            $status = $response->status();
            $body = $response->json() ?? ['raw_body' => $response->body()];

            $this->logApiCall("GET /message_templates", [], $body, $status);

            if (!$response->successful()) {
                $errorMsg = $body['error']['message'] ?? 'Meta API hatası';
                throw new \Exception("WhatsApp şablonları çekilemedi: {$errorMsg} (HTTP {$status})");
            }

            return $body['data'] ?? [];
        } catch (\Exception $e) {
            $this->logApiCall("GET /message_templates (FAILED)", [], ['error' => $e->getMessage()], 500);
            Log::error("WhatsAppClient Şablon Çekme Hatası: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Yapılan API çağrılarını veritabanına loglar.
     */
    protected function logApiCall(string $endpoint, array $request, array $response, int $status): void
    {
        try {
            DB::table('api_logs')->insert([
                'endpoint' => $endpoint,
                'request_payload' => json_encode($request),
                'response_payload' => json_encode($response),
                'http_status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("API Loglama Hatası: " . $e->getMessage());
        }
    }

    /**
     * WhatsApp numarasının durumunu ve kalite puanını Meta'dan çeker.
     *
     * @return array ['quality_rating' => 'GREEN|YELLOW|RED', 'status' => 'CONNECTED|...']
     * @throws \Exception
     */
    public function fetchPhoneNumberStatus(): array
    {
        $endpoint = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}";

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->get($endpoint, [
                    'fields' => 'quality_rating,status'
                ]);

            $status = $response->status();
            $body = $response->json() ?? ['raw_body' => $response->body()];

            $this->logApiCall("GET /phone_number", [], $body, $status);

            if (!$response->successful()) {
                $errorMsg = $body['error']['message'] ?? 'Meta API hatası';
                throw new \Exception("Numara bilgisi çekilemedi: {$errorMsg} (HTTP {$status})");
            }

            return [
                'quality_rating' => $body['quality_rating'] ?? 'UNKNOWN',
                'status' => $body['status'] ?? 'UNKNOWN',
            ];
        } catch (\Exception $e) {
            $this->logApiCall("GET /phone_number (FAILED)", [], ['error' => $e->getMessage()], 500);
            Log::error("WhatsAppClient Numara Durumu Çekme Hatası: " . $e->getMessage());
            throw $e;
        }
    }
}
