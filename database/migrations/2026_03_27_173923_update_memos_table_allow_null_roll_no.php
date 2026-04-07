<?php
// database/migrations/2026_03_27_174000_update_memos_table_allow_null_roll_no.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            // Change the column to allow null
            $table->string('trainee_roll_no')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->string('trainee_roll_no')->nullable(false)->change();
        });
    }
};