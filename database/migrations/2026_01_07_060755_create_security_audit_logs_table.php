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
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // login_failed, login_success, logout, permission_change, etc.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('ip_address', 45); // IPv6 support
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('severity')->default('info'); // info, warning, critical
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('event_type');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('severity');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
