<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->date('held_on')->index();
            $table->time('starts_at')->nullable();
            $table->string('topic')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('leave_count')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->unique(['course_id', 'held_on', 'starts_at']);
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->char('status', 1); // P present, A absent, L late, E excused/leave
            $table->string('remarks')->nullable();
            $table->unique(['lecture_id', 'student_id']);
            $table->index(['student_id', 'status']);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason');
            $table->string('status', 12)->default('pending'); // pending, approved, rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('guardian_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 12)->default('sms'); // sms, whatsapp, email
            $table->string('kind', 24); // absent_today, short_attendance, consecutive_absence
            $table->string('to', 64);
            $table->text('message');
            $table->string('status', 12)->default('queued'); // queued, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_alerts');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('lectures');
    }
};
