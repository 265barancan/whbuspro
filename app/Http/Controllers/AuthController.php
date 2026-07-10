<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Kullanıcı girişi (Login) ve token oluşturma.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Giriş bilgileri hatalı veya kullanıcı bulunamadı.'
            ], 401);
        }

        // Sanctum API Token oluştur
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Giriş başarılı.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Kullanıcı çıkışı (Logout) ve token silme.
     */
    public function logout(Request $request)
    {
        // Mevcut token'ı sil
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Oturum başarıyla sonlandırıldı.'
        ]);
    }

    /**
     * Aktif oturum açmış kullanıcı bilgilerini döner.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
