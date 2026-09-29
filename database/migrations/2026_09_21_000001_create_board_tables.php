<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('machine_id')->unique();
            $table->string('hostname')->nullable();
            $table->string('device_code', 8)->unique();
            $table->char('token_hash', 64)->unique();
            $table->string('approval', 16)->default('pending');
            $table->string('reported_state', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('board_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('code', 512);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index(['board_id', 'expires_at']);
        });

        Schema::create('board_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('status', 16)->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_commands');
        Schema::dropIfExists('board_qr_codes');
        Schema::dropIfExists('boards');
    }
};
