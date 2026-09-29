<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lock_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idle_seconds');
            $table->unsignedSmallInteger('lock_countdown_seconds');
            $table->unsignedSmallInteger('offline_grace_seconds');
            $table->unsignedInteger('emergency_seconds');
            $table->unsignedInteger('session_seconds')->default(0);
            $table->string('emergency_pin_hash')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lock_settings');
    }
};
