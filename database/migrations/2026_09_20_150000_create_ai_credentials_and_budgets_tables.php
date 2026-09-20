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
        Schema::create('ai_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('openrouter');
            $table->string('label', 255)->nullable();
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('team_id', 'ix_ai_credentials_team');
        });

        Schema::create('ai_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique('uq_ai_budgets_team')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_limit_usd', 10, 2)->default(50.00);
            $table->unsignedTinyInteger('alert_at_percent')->default(80);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_budgets');
        Schema::dropIfExists('ai_credentials');
    }
};
