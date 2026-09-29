<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('otp') || Schema::hasColumn('otp', 'type')) {
            return;
        }

        Schema::table('otp', function (Blueprint $table) {
            $table->string('type', 16)->default('login')->after('id');
            $table->timestamp('claimed_at')->nullable()->after('created_at');

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('otp') && Schema::hasColumn('otp', 'type')) {
            Schema::table('otp', function (Blueprint $table) {
                $table->dropIndex(['status', 'type']);
                $table->dropColumn(['type', 'claimed_at']);
            });
        }
    }
};
