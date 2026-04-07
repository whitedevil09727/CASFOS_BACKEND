<?php
// database/migrations/2026_03_27_173000_create_memos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->string('memo_number')->unique();
            $table->unsignedBigInteger('trainee_id');
            $table->string('trainee_name');
            $table->string('trainee_roll_no');
            $table->string('batch_name');
            $table->string('course_name');
            $table->date('date');
            $table->json('absent_sessions');
            $table->string('status')->default('pending_approval'); // Use string instead of enum for PostgreSQL
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();
            
            // Add indexes with custom names
            $table->index(['trainee_id', 'date'], 'idx_memos_trainee_date');
            $table->index('status', 'idx_memos_status');
            $table->index('memo_number', 'idx_memos_number');
            $table->index('generated_at', 'idx_memos_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};