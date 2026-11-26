<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('checkout_key');
            $table->string('environment', 10);
            $table->char('merchant_fingerprint', 64);
            $table->unsignedInteger('amount_irr');
            $table->unsignedSmallInteger('term_days');
            $table->unsignedInteger('daily_verifications');
            $table->string('status', 16);
            $table->char('authority', 36)->nullable();
            $table->string('provider_reference', 24)->nullable();
            $table->unsignedSmallInteger('verified_code')->nullable();
            $table->timestamp('next_verify_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'checkout_key']);
            $table->unique(['environment', 'authority']);
            $table->unique(['environment', 'merchant_fingerprint', 'provider_reference'], 'billing_unique_reference');
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->foreignUuid('invoice_id')->primary()->constrained('billing_invoices')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->unsignedInteger('daily_verifications');
            $table->index(['organization_id', 'expires_at', 'starts_at'], 'billing_current_term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_invoices');
    }
};
