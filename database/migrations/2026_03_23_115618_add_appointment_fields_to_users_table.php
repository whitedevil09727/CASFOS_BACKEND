<?php
// database/migrations/2026_03_23_000002_add_appointment_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('appointed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('appointed_at')->nullable();
            $table->timestamp('term_start')->nullable();
            $table->timestamp('term_end')->nullable();
            $table->boolean('is_current_director')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['appointed_by']);
            $table->dropColumn(['appointed_by', 'appointed_at', 'term_start', 'term_end', 'is_current_director']);
        });
    }
};