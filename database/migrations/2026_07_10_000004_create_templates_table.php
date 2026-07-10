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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_template_name', 150);
            $table->string('language_code', 10)->default('tr');
            $table->string('category', 30)->nullable(); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('status', 20)->nullable();   // APPROVED, PENDING, REJECTED
            $table->tinyInteger('body_variables_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
