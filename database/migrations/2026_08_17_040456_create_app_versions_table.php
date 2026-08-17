<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->string('version_name');
            $table->unsignedInteger('version_code');
            $table->string('apk_path');
            $table->unsignedBigInteger('file_size');
            $table->text('release_notes')->nullable();
            $table->boolean('is_force_update')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();

            // One row per (app, version_code) — also the lookup index for
            // "latest active version" and the gated download route.
            $table->unique(['app_id', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
