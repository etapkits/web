<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('teacher_id')
                ->constrained('users', indexName: 'attendance_sessions_user_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign('attendance_sessions_user_fk');
            $table->dropColumn('user_id');
        });
    }
};
