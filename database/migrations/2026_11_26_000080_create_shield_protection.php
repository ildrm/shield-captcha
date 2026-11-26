<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_protection_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->unsignedInteger('version');
            $table->enum('mode', ['observe', 'enforce']);
            $table->unsignedSmallInteger('window_seconds');
            $table->unsignedSmallInteger('subject_limit');
            $table->unsignedSmallInteger('site_limit');
            $table->string('velocity_decision', 12);
            $table->boolean('require_registered_automation');
            $table->timestamp('created_at');
            $table->unique(['site_id', 'action', 'version'], 'shield_protection_policy_version');
        });

        Schema::create('shield_protection_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->char('event_digest', 64);
            $table->char('request_digest', 64);
            $table->string('action', 64);
            $table->string('product', 12);
            $table->string('environment', 12);
            $table->unsignedInteger('policy_version');
            $table->string('mode', 8);
            $table->string('decision', 12);
            $table->string('would_decision', 12);
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->json('reason_codes');
            $table->string('classification', 24);
            $table->timestamp('retry_at')->nullable();
            $table->string('feedback_label', 12)->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamp('created_at')->index();
            $table->unique(['site_id', 'event_digest'], 'shield_protection_event_replay');
            $table->index(['site_id', 'created_at'], 'shield_protection_site_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_protection_events');
        Schema::dropIfExists('shield_protection_policies');
    }
};
