<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_provider_settings', function (Blueprint $table): void {
            $table->enum('id', ['providers'])->primary();
            $table->boolean('zarinpal_active');
            $table->boolean('stripe_active');
            $table->enum('sms_provider', ['kavenegar', 'twilio'])->nullable();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_provider_settings');
    }
};
