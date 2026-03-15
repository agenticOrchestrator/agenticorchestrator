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
        Schema::create('agent_evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('evaluation_id')->unique();
            $table->string('suite_class');
            $table->string('agent_class');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('passed_cases')->default(0);
            $table->unsignedInteger('failed_cases')->default(0);
            $table->unsignedInteger('error_cases')->default(0);
            $table->decimal('pass_rate', 5, 2)->nullable();
            $table->decimal('average_score', 5, 4)->nullable();
            $table->json('metric_scores')->nullable(); // Per-metric averages
            $table->json('results')->nullable(); // Full results
            $table->decimal('duration_ms', 12, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('agent_class');
            $table->index('team_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_evaluations');
    }
};
