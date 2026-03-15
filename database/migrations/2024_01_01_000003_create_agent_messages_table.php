<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('agent_sessions')->cascadeOnDelete();

            // Message content
            $table->string('role'); // system, user, assistant, tool
            $table->text('content')->nullable();
            $table->string('name')->nullable(); // For tool messages
            $table->string('tool_call_id')->nullable(); // For tool responses

            // Tool calls (for assistant messages)
            $table->json('tool_calls')->nullable();

            // Token usage for this message
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->float('latency_ms')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->string('finish_reason')->nullable(); // stop, length, tool_calls

            $table->timestamps();

            // Index for fetching conversation history
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
