<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->string('provider', 16)->default('zarinpal');
            $table->char('currency', 3)->default('IRR');
            $table->unsignedInteger('amount_minor')->nullable();
            $table->string('stripe_session_id', 191)->nullable()->unique();
            $table->text('stripe_checkout_url')->nullable();
            $table->unsignedInteger('amount_irr')->nullable()->change();
            $table->string('provider_reference', 191)->nullable()->change();
        });

        DB::table('billing_invoices')->whereNull('amount_minor')->update(['amount_minor' => DB::raw('amount_irr')]);
    }

    public function down(): void
    {
        if (DB::table('billing_invoices')->where('provider', '<>', 'zarinpal')->exists()) {
            throw new RuntimeException('Restore a pre-Stripe backup instead of discarding issued payment records.');
        }

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropUnique(['stripe_session_id']);
            $table->dropColumn(['provider', 'currency', 'amount_minor', 'stripe_session_id', 'stripe_checkout_url']);
            $table->unsignedInteger('amount_irr')->change();
            $table->string('provider_reference', 24)->nullable()->change();
        });
    }
};
