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
        Schema::create('sop_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_run_id')->constrained('sop_runs')->cascadeOnDelete();
            $table->string('block_id', 50);
            $table->string('block_type', 50);
            $table->enum('status', [
                'pending',
                'running',
                'awaiting_approval',
                'approved',
                'rejected',
                'skipped',
                'failed',
                'completed',
            ])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('output')->nullable();
            $table->unsignedBigInteger('ai_generation_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('sop_run_id', 'ix_sop_run_steps_run');
            $table->index(['sop_run_id', 'block_id'], 'ix_sop_run_steps_run_block');
            $table->index('status', 'ix_sop_run_steps_status');
            $table->index('assigned_to', 'ix_sop_run_steps_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sop_run_steps');
    }
};
