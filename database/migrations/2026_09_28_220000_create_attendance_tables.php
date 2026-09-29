<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('class_name', 20);
            $table->date('date');
            $table->unsignedTinyInteger('lesson');
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('teacher_name', 100);
            $table->string('teacher_phone', 16);
            $table->unsignedSmallInteger('student_count');
            $table->timestamps();

            $table->unique(['organization_id', 'class_name', 'date', 'lesson'], 'attendance_sessions_slot_unique');
            $table->index(['organization_id', 'date'], 'attendance_sessions_org_date_index');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_no', 20);
            $table->string('student_name', 201);
            $table->string('status', 10);
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_records_session_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
