<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lock_settings') || Schema::hasColumn('lock_settings', 'attendance_sms_enabled')) {
            return;
        }

        Schema::table('lock_settings', function (Blueprint $table) {
            $table->boolean('attendance_sms_enabled')->default(false);
            $table->text('attendance_sms_template')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lock_settings') && Schema::hasColumn('lock_settings', 'attendance_sms_enabled')) {
            Schema::table('lock_settings', function (Blueprint $table) {
                $table->dropColumn(['attendance_sms_enabled', 'attendance_sms_template']);
            });
        }
    }
};
