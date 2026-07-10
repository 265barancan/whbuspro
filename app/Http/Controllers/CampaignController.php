<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Jobs\DispatchCampaignJob;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    /**
     * Kampanyaları istatistikleriyle birlikte listeler.
     */
    public function index(Request $request)
    {
        $campaigns = Campaign::with(['template', 'list'])
            ->withCount([
                'messages as total_count',
                'messages as queued_count' => function ($query) {
                    $query->where('status', 'queued');
                },
                'messages as sent_count' => function ($query) {
                    $query->where('status', 'sent');
                },
                'messages as delivered_count' => function ($query) {
                    $query->where('status', 'delivered');
                },
                'messages as read_count' => function ($query) {
                    $query->where('status', 'read');
                },
                'messages as failed_count' => function ($query) {
                    $query->where('status', 'failed');
                }
            ])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Yeni kampanya oluşturur.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'template_id' => 'required|exists:templates,id',
            'list_id' => 'required|exists:contact_lists,id',
            'throttle_per_minute' => 'nullable|integer|min:1',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $campaign = Campaign::create([
            'name' => $request->input('name'),
            'template_id' => $request->input('template_id'),
            'list_id' => $request->input('list_id'),
            'throttle_per_minute' => $request->input('throttle_per_minute', 60),
            'scheduled_at' => $request->input('scheduled_at'),
            'status' => 'draft'
        ]);

        return response()->json($campaign, 201);
    }

    /**
     * Kampanya detayı ve istatistiklerini döner (Yüzdelik oranlar dahil).
     */
    public function show(Campaign $campaign)
    {
        $campaign->load(['template', 'list']);
        
        $stats = $campaign->messages()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $queued = $stats['queued'] ?? 0;
        $sent = $stats['sent'] ?? 0;
        $delivered = $stats['delivered'] ?? 0;
        $read = $stats['read'] ?? 0;
        $failed = $stats['failed'] ?? 0;
        $total = array_sum($stats);

        // Yüzdelik oranları hesapla (Sıfıra bölünme hatasını önleyerek)
        $percentages = [
            'sent_pct' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'delivered_pct' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'read_pct' => $total > 0 ? round(($read / $total) * 100, 2) : 0,
            'failed_pct' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];

        $formattedStats = [
            'queued' => $queued,
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => $failed,
            'total' => $total
        ];

        return response()->json([
            'campaign' => $campaign,
            'stats' => $formattedStats,
            'percentages' => $percentages
        ]);
    }

    /**
     * Kampanyayı sıraya ekler ve gönderimi tetikler (Dispatch).
     */
    public function trigger(Request $request, Campaign $campaign)
    {
        if ($campaign->status !== 'draft') {
            return response()->json([
                'message' => 'Sadece taslak (draft) durumundaki kampanyalar tetiklenebilir. Mevcut durum: ' . $campaign->status
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'parameters' => 'nullable|array',
            'parameters.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Durumu kuyrukta olarak güncelle
        $campaign->update(['status' => 'queued']);

        // Kuyruk yöneticisi Job'ı tetikle
        DispatchCampaignJob::dispatch($campaign->id, $request->input('parameters', []));

        return response()->json([
            'message' => 'Kampanya başarıyla gönderim kuyruğuna eklendi.',
            'campaign' => $campaign
        ]);
    }

    /**
     * Kampanya kapsamında hata alan mesajları ve nedenlerini döner.
     */
    public function errors(Campaign $campaign)
    {
        $errors = $campaign->messages()
            ->with('contact')
            ->where('status', 'failed')
            ->latest()
            ->paginate(50);

        return response()->json($errors);
    }

    /**
     * Kampanyayı siler.
     */
    public function destroy(Campaign $campaign)
    {
        if (in_array($campaign->status, ['queued', 'sending'])) {
            return response()->json([
                'message' => 'Aktif gönderim sürecindeki kampanyalar silinemez. Önce durdurulmalı veya bitmesi beklenmelidir.'
            ], 400);
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Kampanya başarıyla silindi.'
        ]);
    }
}
