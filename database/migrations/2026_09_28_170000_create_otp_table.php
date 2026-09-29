<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp', function (Blueprint $table) {
            $table->id();
            $table->string('sendto', 20);
            $table->text('message');
            $table->string('status', 16)->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('sended_at')->nullable();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp');
    }
};
