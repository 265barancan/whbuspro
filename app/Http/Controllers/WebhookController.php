<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Contact;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class WebhookController extends Controller
{
    /**
     * Meta Webhook doğrulama (Verification GET) isteğini karşılar.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $localVerifyToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $localVerifyToken) {
            Log::info('Webhook verification successful.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Webhook verification failed: Invalid verify token.', [
            'received_token' => $token,
            'mode' => $mode
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Meta Webhook event (POST) isteklerini karşılar.
     */
    public function handle(Request $request)
    {
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('X-Hub-Signature-256');

        // 1. İmza Doğrulama (Signature Validation)
        $appSecret = config('services.whatsapp.app_secret');
        if (empty($appSecret)) {
            Log::warning('WHATSAPP_APP_SECRET is not configured. Webhook signature validation skipped.');
        } else {
            if (!$signatureHeader) {
                return response('Missing signature header', 401);
            }

            $signature = str_replace('sha256=', '', $signatureHeader);
            $expectedSignature = hash_hmac('sha256', $rawBody, $appSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Webhook signature validation failed.', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response('Invalid signature', 401);
            }
        }

        $payload = json_decode($rawBody, true) ?? [];

        // 2. Ham API Çağrısını Logla (api_logs)
        $this->logWebhookCall($payload);

        // 3. Payload İşleme (Processing)
        try {
            if (isset($payload['entry']) && is_array($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    if (isset($entry['changes']) && is_array($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            $value = $change['value'] ?? [];
                            $field = $change['field'] ?? '';

                            if ($field === 'messages') {
                                // A. Mesaj Durum Güncellemelerini (Statuses) İşle
                                if (isset($value['statuses']) && is_array($value['statuses'])) {
                                    $this->processStatuses($value['statuses']);
                                }

                                // B. Gelen (Inbound) Mesajları İşle
                                if (isset($value['messages']) && is_array($value['messages'])) {
                                    $this->processInboundMessages($value['messages']);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing webhook payload: ' . $e->getMessage(), [
                'exception' => $e,
                'payload' => $payload
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Webhook durum güncellemelerini (sent, delivered, read, failed) işler.
     */
    protected function processStatuses(array $statuses): void
    {
        foreach ($statuses as $statusUpdate) {
            $waMessageId = $statusUpdate['id'] ?? null;
            $status = $statusUpdate['status'] ?? null;
            $timestamp = $statusUpdate['timestamp'] ?? null;

            if (!$waMessageId || !$status) {
                continue;
            }

            $message = Message::where('wa_message_id', $waMessageId)->first();
            if (!$message) {
                Log::debug("Message with wa_message_id '{$waMessageId}' not found in database. Skipping update.");
                continue;
            }

            $updateData = ['status' => $status];
            $dateTime = $timestamp ? Carbon::createFromTimestamp($timestamp) : now();

            if ($status === 'sent') {
                $updateData['sent_at'] = $dateTime;
            } elseif ($status === 'delivered') {
                $updateData['delivered_at'] = $dateTime;
            } elseif ($status === 'read') {
                $updateData['read_at'] = $dateTime;
            } elseif ($status === 'failed') {
                $updateData['status'] = 'failed';
                if (isset($statusUpdate['errors']) && is_array($statusUpdate['errors'])) {
                    $errors = [];
                    foreach ($statusUpdate['errors'] as $err) {
                        $errors[] = ($err['title'] ?? '') . ': ' . ($err['message'] ?? '');
                    }
                    $updateData['error_message'] = implode(' | ', $errors);
                } else {
                    $updateData['error_message'] = 'Meta API delivery failure';
                }
            }

            $message->update($updateData);

            Log::info("Message status updated.", [
                'wa_message_id' => $waMessageId,
                'status' => $status
            ]);
        }
    }

    /**
     * Gelen inbound mesajları işler (STOP kelimesini yakalama ve opted-out yönetimi).
     */
    protected function processInboundMessages(array $messages): void
    {
        foreach ($messages as $inboundMessage) {
            $from = $inboundMessage['from'] ?? null;
            $type = $inboundMessage['type'] ?? null;

            if (!$from) {
                continue;
            }

            $messageBody = '';
            if ($type === 'text' && isset($inboundMessage['text']['body'])) {
                $messageBody = trim($inboundMessage['text']['body']);
            } elseif ($type === 'button' && isset($inboundMessage['button']['text'])) {
                $messageBody = trim($inboundMessage['button']['text']);
            } elseif ($type === 'interactive') {
                // Interactive mesaj türlerinden buton veya list seçimlerini al
                $interactive = $inboundMessage['interactive'] ?? [];
                if (isset($interactive['button_reply']['title'])) {
                    $messageBody = trim($interactive['button_reply']['title']);
                } elseif (isset($interactive['list_reply']['title'])) {
                    $messageBody = trim($interactive['list_reply']['title']);
                }
            }

            if (empty($messageBody)) {
                continue;
            }

            // Regex ile STOP, DUR, IPTAL kelimelerini denetle (case-insensitive)
            if (preg_match('/^(dur|stop|iptal|unsubscribe)/i', $messageBody)) {
                $this->handleOptOut($from);
            }
        }
    }

    /**
     * Kullanıcı STOP mesajı attığında opted-out işlemini yapar.
     */
    protected function handleOptOut(string $phoneNumber): void
    {
        // Gelen numara genellikle + olmadan E.164 formatındadır. DB'de temiz numara ile eşleştir.
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);

        $contact = Contact::whereRaw("REPLACE(phone_number, '+', '') = ?", [$cleanPhone])->first();

        if ($contact) {
            DB::transaction(function () use ($contact) {
                $contact->update([
                    'opted_in' => false,
                    'opted_out_at' => now(),
                    'status' => 'blocked' // Politika gereği otomatik engellenir
                ]);
            });

            Log::info("Contact opted-out and was automatically blocked via inbound STOP message.", [
                'contact_id' => $contact->id,
                'phone_number' => $contact->phone_number
            ]);
        } else {
            // Sistemde kayıtlı olmayan bir numara ise, yine de kara liste / engelli olarak kaydedelim
            Contact::create([
                'phone_number' => '+' . $cleanPhone,
                'opted_in' => false,
                'opted_out_at' => now(),
                'status' => 'blocked'
            ]);
            Log::info("Unknown contact sent STOP message. Created as blocked contact.", [
                'phone_number' => '+' . $cleanPhone
            ]);
        }
    }

    /**
     * Webhook çağrılarını api_logs tablosuna kaydeder.
     */
    protected function logWebhookCall(array $payload): void
    {
        try {
            ApiLog::create([
                'endpoint' => 'POST /webhook',
                'request_payload' => $payload,
                'response_payload' => ['status' => 'received'],
                'http_status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook call to database: ' . $e->getMessage());
        }
    }
}
