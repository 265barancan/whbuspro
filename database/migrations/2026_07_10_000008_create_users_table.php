<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'operator'])->default('operator');
            $table->rememberToken();
            $table->timestamps();
        });

        // Varsayılan Admin ve Operatör kullanıcılarını ekle (Kolay test için)
        DB::table('users')->insert([
            [
                'name' => 'Can Baran',
                'email' => 'admin@whbuspro.com',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Operator User',
                'email' => 'operator@whbuspro.com',
                'password' => Hash::make('operator12345'),
                'role' => 'operator',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
