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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('template_id')->constrained('templates')->onDelete('restrict');
            $table->foreignId('list_id')->constrained('contact_lists')->onDelete('restrict');
            $table->enum('status', ['draft', 'queued', 'sending', 'completed', 'failed', 'paused'])->default('draft');
            $table->integer('throttle_per_minute')->default(60); // Max sends per minute
            $table->dateTime('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
