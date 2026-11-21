<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One student per admission application, even if two admit requests race.
        Schema::table('students', function (Blueprint $table) {
            $table->unique('admission_application_id');
        });

        // Who filed a leave request (the filer cannot approve their own).
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('filed_by')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filed_by');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['admission_application_id']);
        });
    }
};
