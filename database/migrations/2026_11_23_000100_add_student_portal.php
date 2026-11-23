<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Student portal sign-in: roll number + password (first password = the admission PIN).
        Schema::table('students', function (Blueprint $table) {
            $table->string('password')->nullable()->after('status');
            $table->boolean('must_change_password')->default(true)->after('password');
            $table->rememberToken();
            $table->timestamp('portal_last_login_at')->nullable();
        });

        // Leave asked for by the student in the portal (decided by the principal's office).
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('source', 16)->default('staff')->after('filed_by'); // staff | student
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['password', 'must_change_password', 'remember_token', 'portal_last_login_at']));
    }
};
