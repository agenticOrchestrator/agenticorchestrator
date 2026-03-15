<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_usage_logs', function (Blueprint $table) {
            $table->id();

            // What was used
            $table->string('agent_name')->index();
            $table->string('provider');
            $table->string('model');
            $table->string('operation')->default('chat'); // chat, stream, embed, evaluate

            // Who used it
            $table->nullableMorphs('tenant');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();

            // Token usage
            $table->unsignedInteger('prompt_tokens');
            $table->unsignedInteger('completion_tokens');
            $table->unsignedInteger('total_tokens');

            // Cost (in USD)
            $table->decimal('input_cost', 10, 6);
            $table->decimal('output_cost', 10, 6);
            $table->decimal('total_cost', 10, 6);

            // Performance
            $table->float('latency_ms');
            $table->boolean('cached')->default(false);
            $table->boolean('streamed')->default(false);

            // Tool usage
            $table->unsignedInteger('tool_calls')->default(0);
            $table->json('tools_used')->nullable();

            // Outcome
            $table->string('status'); // success, error, rate_limited, timeout
            $table->string('finish_reason')->nullable();
            $table->text('error_message')->nullable();

            // Request/Response (optional, controlled by config)
            $table->text('request_hash')->nullable(); // For cache lookup
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();

            $table->timestamps();

            // Indexes for analytics
            $table->index(['tenant_type', 'tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['agent_name', 'created_at']);
            $table->index(['provider', 'model', 'created_at']);
            $table->index('created_at'); // For time-based queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_usage_logs');
    }
};
