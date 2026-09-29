<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lock_settings') || Schema::hasColumn('lock_settings', 'session_seconds')) {
            return;
        }

        Schema::table('lock_settings', function (Blueprint $table) {
            $table->unsignedInteger('session_seconds')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lock_settings') && Schema::hasColumn('lock_settings', 'session_seconds')) {
            Schema::table('lock_settings', function (Blueprint $table) {
                $table->dropColumn('session_seconds');
            });
        }
    }
};
