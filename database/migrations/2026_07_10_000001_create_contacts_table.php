<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->string('full_name', 150)->nullable();
            $table->boolean('opted_in')->default(false);
            $table->dateTime('opted_in_at')->nullable();
            $table->dateTime('opted_out_at')->nullable();
            $table->enum('status', ['active', 'blocked', 'invalid'])->default('active');
            $table->timestamps();

            // Indexes
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
