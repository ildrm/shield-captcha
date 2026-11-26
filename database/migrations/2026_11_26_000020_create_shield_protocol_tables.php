<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->char('secret_digest', 64)->unique();
            $table->string('prefix', 20);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'scope', 'revoked_at']);
        });

        Schema::create('shield_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('origin', 255);
            $table->string('environment', 12);
            $table->char('salt', 32);
            $table->char('challenge_digest', 64);
            $table->unsignedInteger('max_number');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->index(['site_id', 'solved_at', 'expires_at']);
        });

        Schema::create('shield_tokens', function (Blueprint $table): void {
            $table->char('digest', 64)->primary();
            $table->foreignUuid('challenge_id')->unique()->constrained('shield_challenges')->cascadeOnDelete();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->index();
        });

        Schema::create('shield_rate_windows', function (Blueprint $table): void {
            $table->char('bucket', 64)->primary();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('expires_at')->index();
        });

        Schema::create('shield_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('environment', 12);
            $table->timestamp('occurred_at')->index();
            $table->index(['site_id', 'environment', 'occurred_at']);
        });

        Schema::create('shield_sms_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->char('event_digest', 64);
            $table->char('request_digest', 64);
            $table->string('action', 64);
            $table->string('environment', 12);
            $table->string('decision', 16);
            $table->string('reason_code', 32);
            $table->timestamp('retry_at')->nullable();
            $table->timestamp('created_at')->index();
            $table->unique(['site_id', 'event_digest']);
            $table->index(['site_id', 'environment', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_sms_assessments');
        Schema::dropIfExists('shield_usage_events');
        Schema::dropIfExists('shield_rate_windows');
        Schema::dropIfExists('shield_tokens');
        Schema::dropIfExists('shield_challenges');
        Schema::dropIfExists('site_credentials');
    }
};
