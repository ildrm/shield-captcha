<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('term_days');
            $table->unsignedInteger('daily_verifications');
            $table->unsignedInteger('zarinpal_amount_irr')->nullable();
            $table->unsignedInteger('stripe_amount_minor')->nullable();
            $table->char('stripe_currency', 3)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->foreignId('billing_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('plan_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billing_plan_id');
            $table->dropColumn('plan_name');
        });
        Schema::dropIfExists('billing_plans');
    }
};
