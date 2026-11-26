<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 48);
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('shield_key_displays', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('session_digest', 64);
            $table->text('encrypted_key');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_key_displays');
        Schema::dropIfExists('shield_audit_entries');
    }
};
