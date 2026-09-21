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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug'], 'uq_skills_team_slug');
            $table->index('team_id', 'ix_skills_team');
        });

        Schema::create('skill_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('instructions');
            $table->json('variables')->nullable();
            $table->enum('source', ['manual', 'openrouter', 'import_markdown'])->default('manual');
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['skill_id', 'version_number'], 'uq_skill_versions');
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->foreign('current_version_id', 'fk_skills_current_version')
                ->references('id')
                ->on('skill_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeign('fk_skills_current_version');
        });

        Schema::dropIfExists('skill_versions');
        Schema::dropIfExists('skills');
    }
};
