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
        // 1. Usuarios externos con acceso al portal de un cliente
        Schema::create('client_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'collaborator'])->default('collaborator');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'user_id'], 'uq_client_user');
        });

        // 2. Invitaciones con enlace de un solo uso y expiración
        Schema::create('client_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['owner', 'collaborator'])->default('collaborator');
            $table->string('token', 64)->unique('uq_client_invitations_token');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'email'], 'ix_client_invitations_email');
            $table->index('token', 'ix_client_invitations_tok');
        });

        // 3. Archivos subidos por clientes
        Schema::create('client_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_run_id')->nullable()->constrained('sop_runs')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index(['team_id', 'client_id'], 'ix_client_files_team_client');
            $table->index('sop_run_id', 'ix_client_files_run');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_files');
        Schema::dropIfExists('client_invitations');
        Schema::dropIfExists('client_user');
    }
};
