<?php
// database/migrations/2024_03_25_000001_create_attendances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trainee_id')->nullable()->constrained('trainees')->onDelete('set null');
            $table->foreignId('timetable_session_id')->constrained('timetable_sessions')->onDelete('cascade');
            $table->date('attendance_date');
            $table->enum('status', ['Present', 'Absent', 'On Leave', 'Not Marked'])->default('Not Marked');
            $table->timestamp('marked_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_disputed')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint to prevent duplicate attendance for same session on same day
            $table->unique(['user_id', 'timetable_session_id', 'attendance_date'], 'unique_attendance');
            
            // Indexes
            $table->index('user_id');
            $table->index('attendance_date');
            $table->index('status');
            $table->index('timetable_session_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};