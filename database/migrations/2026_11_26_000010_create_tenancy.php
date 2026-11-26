<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_operator')->default(false);
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 36)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
            $table->index(['user_id', 'organization_id']);
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('public_key', 64)->unique();
            $table->string('environment', 12);
            $table->string('origin', 255)->index();
            $table->string('domain_verification_token', 80)->nullable();
            $table->timestamp('domain_verified_at')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'environment', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_operator', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
