<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('devices_targeted')->default(0);
            $table->unsignedInteger('devices_sent')->default(0);
            $table->unsignedInteger('devices_failed')->default(0);
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_logs');
    }
};
