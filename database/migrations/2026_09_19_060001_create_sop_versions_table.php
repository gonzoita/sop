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
        Schema::create('sop_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_id')->constrained('sops')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('blocks');
            $table->text('changelog')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['sop_id', 'version_number'], 'uq_sop_versions');
            $table->index('team_id', 'ix_sop_versions_team');
            $table->index('published_at', 'ix_sop_versions_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sop_versions');
    }
};