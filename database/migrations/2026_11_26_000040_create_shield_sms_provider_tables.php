<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_sms_tenant_keys', function (Blueprint $table): void {
            $table->foreignId('organization_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('digest_key');
        });

        Schema::create('shield_sms_kavenegar_sites', function (Blueprint $table): void {
            $table->foreignId('site_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('template', 64);
            $table->text('api_key');
            $table->timestamps();
        });

        Schema::create('shield_sms_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->uuid('assessment_id');
            $table->char('event_digest', 64);
            $table->char('request_digest', 64);
            $table->string('state', 16);
            $table->string('provider_reference', 20)->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'event_digest']);
            $table->index(['site_id', 'created_at']);
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_sms_deliveries');
        Schema::dropIfExists('shield_sms_kavenegar_sites');
        Schema::dropIfExists('shield_sms_tenant_keys');
    }
};
