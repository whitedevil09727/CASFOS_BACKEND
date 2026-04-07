<?php
// database/migrations/2026_03_24_142351_create_timetable_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('day', 3); // Mon, Tue, Wed, Thu, Fri
            $table->integer('start_hour'); // 8-17 (8 AM to 5 PM)
            $table->integer('duration'); // 1-4 hours
            $table->string('subject');
            $table->string('faculty');
            $table->string('topic')->nullable();
            $table->string('room')->nullable();
            $table->boolean('is_substituted')->default(false);
            $table->string('original_faculty')->nullable();
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
            $table->foreignId('batch_id')->nullable()->constrained('batches')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('day');
            $table->index('start_hour');
            $table->index('faculty');
            $table->index('course_id');
            $table->index('batch_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('timetable_sessions');
    }
};