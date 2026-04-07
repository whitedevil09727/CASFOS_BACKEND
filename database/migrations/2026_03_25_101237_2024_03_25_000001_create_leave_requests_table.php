<?php
// database/migrations/2024_03_25_000001_create_leave_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trainee_id')->nullable()->constrained('trainees')->onDelete('cascade');
            $table->string('leave_type'); // Medical, Personal, Earned, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days')->nullable();
            $table->text('reason');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('leave_type');
            $table->index('start_date');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};