<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Agent reference
            $table->string('agent_name')->index();
            $table->foreignId('agent_definition_id')->nullable()->constrained()->nullOnDelete();

            // Tenant scoping
            $table->nullableMorphs('tenant');

            // User who initiated the session
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Session state
            $table->string('status')->default('active'); // active, completed, failed, expired
            $table->json('context')->nullable(); // Additional context data
            $table->json('metadata')->nullable(); // Custom metadata

            // Usage tracking
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('tool_call_count')->default(0);
            $table->float('total_latency_ms')->default(0);

            // Timestamps
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_type', 'tenant_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
