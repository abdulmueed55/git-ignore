<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained();
            $table->string('title');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->unsignedInteger('application_fee')->default(0);
            $table->string('status', 16)->default('open'); // draft, open, closed, archived
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->foreignId('second_program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->string('tracking_no', 24)->unique();
            $table->string('access_pin', 8); // applicant uses tracking no + pin to resume / check status

            // Applicant identity
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('cnic', 20)->nullable()->index(); // CNIC / B-Form
            $table->string('father_cnic', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 12)->nullable();
            $table->string('religion', 24)->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('domicile', 64)->nullable();
            $table->string('quota', 24)->default('open'); // open, sports, hafiz, disabled, fg_employee, army
            $table->boolean('is_hafiz')->default(false);

            // Academic record (SSC / HSSC)
            $table->string('matric_board', 64)->nullable();
            $table->string('matric_roll_no', 24)->nullable();
            $table->unsignedSmallInteger('matric_year')->nullable();
            $table->unsignedSmallInteger('matric_obtained')->nullable();
            $table->unsignedSmallInteger('matric_total')->nullable();
            $table->string('matric_group', 32)->nullable();
            $table->string('inter_board', 64)->nullable();
            $table->string('inter_roll_no', 24)->nullable();
            $table->unsignedSmallInteger('inter_year')->nullable();
            $table->unsignedSmallInteger('inter_obtained')->nullable();
            $table->unsignedSmallInteger('inter_total')->nullable();
            $table->string('inter_group', 32)->nullable();

            // Scoring & AI verification
            $table->decimal('merit_score', 6, 3)->nullable()->index();
            $table->unsignedTinyInteger('verification_score')->nullable(); // 0-100 document consistency
            $table->string('verification_status', 16)->default('pending'); // pending, processing, clear, review, mismatch
            $table->json('autofilled_fields')->nullable(); // which fields came from documents

            $table->string('status', 20)->default('draft')->index(); // draft, submitted, under_review, verified, flagged, selected, admitted, rejected, waitlisted
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // cnic, father_cnic, matric_dmc, inter_dmc, photo, domicile, character_certificate
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 64);
            $table->unsignedInteger('size')->default(0);
            $table->string('extraction_status', 16)->default('pending'); // pending, processing, done, failed, skipped
            $table->json('extracted')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('extraction_error')->nullable();
            $table->string('extracted_by', 32)->nullable(); // model id or "manual"
            $table->timestamp('extracted_at')->nullable();
            $table->unique(['admission_application_id', 'type']);
            $table->timestamps();
        });

        Schema::create('verification_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained()->cascadeOnDelete();
            $table->string('field', 32); // name, father_name, cnic, date_of_birth, matric_obtained ...
            $table->string('label');
            $table->string('form_value')->nullable();
            $table->json('document_values')->nullable(); // {"cnic":"...","matric_dmc":"..."}
            $table->string('result', 12); // match, partial, mismatch, missing
            $table->unsignedTinyInteger('similarity')->default(0);
            $table->string('note')->nullable();
            $table->boolean('overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('merit_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->unsignedTinyInteger('list_no')->default(1);
            $table->decimal('closing_merit', 6, 3)->nullable();
            $table->unsignedSmallInteger('seats_offered')->default(0);
            $table->string('status', 16)->default('draft'); // draft, published
            $table->timestamp('published_at')->nullable();
            $table->date('fee_deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('merit_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merit_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('merit_score', 6, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_list_entries');
        Schema::dropIfExists('merit_lists');
        Schema::dropIfExists('verification_checks');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('admission_cycles');
    }
};
