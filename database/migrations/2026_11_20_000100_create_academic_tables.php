<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 16)->unique();
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('designation')->constrained()->nullOnDelete();
        });

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. 2026–2028
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 24)->unique();
            $table->string('level', 24); // intermediate, adp, bs
            $table->unsignedTinyInteger('duration_years')->default(2);
            $table->unsignedSmallInteger('seats')->default(100);
            $table->string('shift', 16)->default('morning');
            $table->string('eligibility')->nullable();
            $table->decimal('min_percentage', 5, 2)->default(45);
            $table->json('merit_weights')->nullable(); // {"matric":1,"inter":0,"test":0,"hafiz_bonus":20}
            $table->unsignedInteger('annual_fee')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Part-I · A
            $table->unsignedTinyInteger('year')->default(1);
            $table->string('room', 32)->nullable();
            $table->foreignId('incharge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(50);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 24)->unique();
            $table->unsignedTinyInteger('credit_hours')->default(3);
            $table->boolean('has_practical')->default(false);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            // A subject taught to a section by a teacher
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['section_id', 'subject_id']);
            $table->timestamps();
        });

        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = Monday … 6 = Saturday
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained();
            $table->unsignedBigInteger('admission_application_id')->nullable()->index();
            $table->string('roll_no', 32)->unique();
            $table->string('registration_no', 40)->nullable()->unique();
            $table->string('name');
            $table->string('father_name');
            $table->string('cnic', 20)->nullable()->index(); // CNIC or B-Form
            $table->string('father_cnic', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 12)->default('male');
            $table->string('phone', 32)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('domicile', 64)->nullable();
            $table->string('blood_group', 4)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 16)->default('active'); // active, struck_off, freezed, graduated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('timetable_slots');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('academic_sessions');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('department_id'));
        Schema::dropIfExists('departments');
    }
};
