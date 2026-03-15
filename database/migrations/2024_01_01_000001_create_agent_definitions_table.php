<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('class_name');
            $table->text('description')->nullable();
            $table->string('provider')->default('openai');
            $table->string('model')->default('gpt-4o');
            $table->text('instructions')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('tools')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true);

            // Tenant scoping - uses morphs for flexibility with different tenant models
            $table->nullableMorphs('tenant');

            // Creator tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Unique name per tenant
            $table->unique(['name', 'tenant_type', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_definitions');
    }
};
