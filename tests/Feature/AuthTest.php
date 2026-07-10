<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Test yöneticisi oluştur (Migration içindeki DB insert zaten çalışacak ancak emin olalım)
        $this->admin = User::where('email', 'admin@whbuspro.com')->first() 
            ?? User::create([
                'name' => 'Can Baran',
                'email' => 'admin@whbuspro.com',
                'password' => 'admin12345',
                'role' => 'admin'
            ]);
    }

    /**
     * Test successful login returns bearer token.
     */
    public function test_login_successful(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@whbuspro.com',
            'password' => 'admin12345'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'user'
        ]);
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@whbuspro.com',
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Giriş bilgileri hatalı veya kullanıcı bulunamadı.']);
    }

    /**
     * Test endpoints are protected by Sanctum auth middleware.
     */
    public function test_endpoints_are_protected_by_auth(): void
    {
        // Token olmadan korumalı bir endpoint'i çağır
        $response = $this->getJson('/api/contacts');

        $response->assertStatus(401);
    }

    /**
     * Test successful logout revokes token.
     */
    public function test_logout_successful(): void
    {
        // 1. Giriş yap ve token al
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@whbuspro.com',
            'password' => 'admin12345'
        ]);
        $token = $loginResponse->json('access_token');

        // 2. Token ile korumalı endpoint'e erişebildiğini doğrula
        $meResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/auth/me');
        $meResponse->assertStatus(200);

        // 3. Çıkış yap
        $logoutResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/auth/logout');
        $logoutResponse->assertStatus(200);

        // 4. Tekrar erişmeye çalıştığında yetkisiz olduğunu doğrula
        $meResponseRetry = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/auth/me');
        $meResponseRetry->assertStatus(401);
    }
}
