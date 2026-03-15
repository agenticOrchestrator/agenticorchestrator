<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->id();
            $table->string('namespace')->index(); // Team/agent scoped namespace

            // Memory identification
            $table->string('key')->index();
            $table->string('type')->default('general'); // general, fact, preference, context

            // Content
            $table->text('content');
            $table->json('metadata')->nullable();

            // Tenant and agent scoping
            $table->nullableMorphs('tenant');
            $table->string('agent_name')->nullable()->index();
            $table->foreignId('session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();

            // User scoping (for user-specific memories)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Importance and retrieval scoring
            $table->float('importance')->default(0.5);
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();

            // Expiration
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Unique key per namespace
            $table->unique(['namespace', 'key']);

            // Index for namespace-based lookups
            $table->index(['namespace', 'type']);
            $table->index(['tenant_type', 'tenant_id', 'agent_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
