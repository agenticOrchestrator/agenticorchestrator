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
        Schema::create('agent_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('agent_class');
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->decimal('latency_ms', 12, 2)->default(0);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_class');
            $table->index('model');
            $table->index('team_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['team_id', 'created_at']);
            $table->index(['agent_class', 'team_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_usage_logs');
    }
};
