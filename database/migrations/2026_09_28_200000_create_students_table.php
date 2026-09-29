<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('student_no', 20);
            $table->string('first_name', 40);
            $table->string('last_name', 40);
            $table->string('class_name', 20);
            $table->string('parent_phone', 16)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'student_no']);
            $table->index(['organization_id', 'class_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
