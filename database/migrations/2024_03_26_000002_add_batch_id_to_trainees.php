<?php
// database/migrations/2024_03_26_000002_add_batch_id_to_trainees.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            if (!Schema::hasColumn('trainees', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('user_id')->constrained('batches')->onDelete('set null');
                $table->index('batch_id');
            }
        });
    }
    
    public function down(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};