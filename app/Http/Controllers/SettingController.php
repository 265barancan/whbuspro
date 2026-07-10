<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    /**
     * WhatsApp API ayarlarını döner.
     */
    public function getWhatsAppSettings()
    {
        $settings = [
            'whatsapp_api_url' => Setting::get('whatsapp_api_url', 'https://graph.facebook.com'),
            'whatsapp_api_version' => Setting::get('whatsapp_api_version', 'v20.0'),
            'whatsapp_phone_number_id' => Setting::get('whatsapp_phone_number_id', ''),
            'whatsapp_business_account_id' => Setting::get('whatsapp_business_account_id', ''),
            'whatsapp_token' => Setting::get('whatsapp_token', ''),
            'whatsapp_app_secret' => Setting::get('whatsapp_app_secret', ''),
            'whatsapp_verify_token' => Setting::get('whatsapp_verify_token', ''),
        ];

        return response()->json($settings);
    }

    /**
     * WhatsApp API ayarlarını günceller.
     */
    public function updateWhatsAppSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_api_url' => 'required|url',
            'whatsapp_api_version' => 'required|string',
            'whatsapp_phone_number_id' => 'required|string',
            'whatsapp_business_account_id' => 'required|string',
            'whatsapp_token' => 'required|string',
            'whatsapp_app_secret' => 'required|string',
            'whatsapp_verify_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Setting::set('whatsapp_api_url', $request->input('whatsapp_api_url'));
        Setting::set('whatsapp_api_version', $request->input('whatsapp_api_version'));
        Setting::set('whatsapp_phone_number_id', $request->input('whatsapp_phone_number_id'));
        Setting::set('whatsapp_business_account_id', $request->input('whatsapp_business_account_id'));
        Setting::set('whatsapp_token', $request->input('whatsapp_token'));
        Setting::set('whatsapp_app_secret', $request->input('whatsapp_app_secret'));
        Setting::set('whatsapp_verify_token', $request->input('whatsapp_verify_token'));

        return response()->json([
            'message' => 'WhatsApp ayarları başarıyla güncellendi.'
        ]);
    }
}
