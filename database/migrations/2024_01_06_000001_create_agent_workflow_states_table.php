<?php

declare(strict_types=1);

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
        Schema::create('agent_workflow_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('execution_id')->unique()->index();
            $table->string('workflow_class');
            $table->string('status')->default('pending')->index();
            $table->json('input')->nullable();
            $table->json('state')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->string('paused_at_step')->nullable();
            $table->decimal('duration_ms', 12, 2)->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index for finding resumable workflows
            $table->index(['status', 'tenant_id']);

            // Index for pruning old completed workflows
            $table->index(['status', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_workflow_states');
    }
};
