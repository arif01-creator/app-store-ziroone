<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->string('install_uuid');
            $table->text('fcm_token')->nullable();
            $table->string('device_model')->nullable();
            $table->string('android_version')->nullable();
            $table->string('client_label')->nullable();
            $table->unsignedInteger('current_version_code')->nullable();
            $table->string('current_version_name')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['app_id', 'install_uuid']);
            // Dashboard "recent check-ins" ordering.
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
