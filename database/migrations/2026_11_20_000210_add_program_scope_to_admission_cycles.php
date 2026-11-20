<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            // Programs offered in this cycle; null = every active program.
            $table->json('program_ids')->nullable()->after('application_fee');
        });
    }

    public function down(): void
    {
        Schema::table('admission_cycles', fn (Blueprint $t) => $t->dropColumn('program_ids'));
    }
};
