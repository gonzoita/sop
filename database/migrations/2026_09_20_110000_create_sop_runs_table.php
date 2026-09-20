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
        Schema::create('sop_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_id')->constrained('sops')->cascadeOnDelete();
            $table->foreignId('sop_version_id')->constrained('sop_versions')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('title');
            $table->enum('status', [
                'pending',
                'in_progress',
                'awaiting_input',
                'awaiting_approval',
                'completed',
                'cancelled',
            ])->default('in_progress');
            $table->json('inputs')->nullable();
            $table->json('outputs')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('team_id', 'ix_sop_runs_team');
            $table->index('sop_id', 'ix_sop_runs_sop');
            $table->index('client_id', 'ix_sop_runs_client');
            $table->index('status', 'ix_sop_runs_status');
            $table->index('assigned_to', 'ix_sop_runs_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sop_runs');
    }
};
