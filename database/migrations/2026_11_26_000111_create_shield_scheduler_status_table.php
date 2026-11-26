<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_scheduler_status', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_scheduler_status');
    }
};
