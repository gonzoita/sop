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
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_run_id')->nullable()->constrained('sop_runs')->nullOnDelete();
            $table->foreignId('sop_run_step_id')->nullable()->constrained('sop_run_steps')->nullOnDelete();
            $table->foreignId('skill_version_id')->nullable()->constrained('skill_versions')->nullOnDelete();
            $table->string('provider', 50)->default('openrouter');
            $table->string('model', 190);
            $table->longText('prompt');
            $table->longText('response')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->enum('status', ['queued', 'running', 'succeeded', 'failed'])->default('queued');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at'], 'ix_ai_gen_team_created');
            $table->index('status', 'ix_ai_gen_status');
            $table->index('sop_run_id', 'ix_ai_gen_run');
        });

        Schema::table('sop_run_steps', function (Blueprint $table) {
            $table->foreign('ai_generation_id', 'fk_run_steps_generation')
                ->references('id')
                ->on('ai_generations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sop_run_steps', function (Blueprint $table) {
            $table->dropForeign('fk_run_steps_generation');
        });

        Schema::dropIfExists('ai_generations');
    }
};
