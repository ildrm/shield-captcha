<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_sms_twilio_sites', function (Blueprint $table): void {
            $table->foreignId('site_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->text('credentials');
            $table->timestamps();
        });

        Schema::table('shield_sms_deliveries', function (Blueprint $table): void {
            $table->string('provider', 16)->default('kavenegar');
            $table->string('provider_reference', 34)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('shield_sms_twilio_sites')->exists() || DB::table('shield_sms_deliveries')->where('provider', 'twilio')->exists()) {
            throw new RuntimeException('Restore a pre-upgrade database backup to roll back provisioned Twilio credentials or delivery history.');
        }

        Schema::table('shield_sms_deliveries', function (Blueprint $table): void {
            $table->dropColumn('provider');
            $table->string('provider_reference', 20)->nullable()->change();
        });

        Schema::dropIfExists('shield_sms_twilio_sites');
    }
};
