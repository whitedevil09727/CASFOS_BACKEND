<?php
// database/migrations/2026_04_04_120000_create_feedback_management_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackManagementTables extends Migration  // Changed class name
{
    public function up()
    {
        // Faculty subjects mapping table
        if (!Schema::hasTable('faculty_subjects')) {
            Schema::create('faculty_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('faculty_id')->constrained('faculty_profiles')->onDelete('cascade');
                $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
                $table->enum('syllabus_status', ['pending', 'in_progress', 'completed'])->default('pending');
                $table->boolean('feedback_unlocked')->default(false);
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamp('deadline_at')->nullable();
                $table->timestamps();
                
                $table->unique(['faculty_id', 'course_id']);
            });
        }

        // Feedback responses table
        if (!Schema::hasTable('feedback_responses')) {
            Schema::create('feedback_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('faculty_id')->constrained('faculty_profiles')->onDelete('cascade');
                $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
                $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
                
                // Rating fields (1-5)
                $table->integer('content_relevance')->nullable();
                $table->integer('structure')->nullable();
                $table->integer('clarity')->nullable();
                $table->integer('methodology')->nullable();
                $table->integer('vertical_learning')->nullable();
                $table->integer('lateral_learning')->nullable();
                $table->decimal('overall_rating', 3, 1)->nullable();
                
                // Duration rating
                $table->enum('duration_rating', ['too_short', 'appropriate', 'too_long'])->nullable();
                
                // Qualitative feedback
                $table->text('positive_feedback')->nullable();
                $table->text('improvement_suggestions')->nullable();
                $table->text('additional_comments')->nullable();
                
                // Status
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                
                $table->timestamps();
                
                $table->unique(['trainee_id', 'faculty_id', 'course_id']);
                $table->index(['faculty_id', 'course_id', 'status']);
            });
        }

        // Final feedback table
        if (!Schema::hasTable('final_feedback')) {
            Schema::create('final_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
                $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
                
                $table->integer('overall_rating')->nullable();
                $table->integer('course_content')->nullable();
                $table->integer('teaching_quality')->nullable();
                $table->integer('infrastructure')->nullable();
                $table->integer('placement_support')->nullable();
                
                $table->text('strengths')->nullable();
                $table->text('areas_for_improvement')->nullable();
                $table->text('recommendations')->nullable();
                
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                
                $table->timestamps();
                
                $table->unique(['trainee_id', 'batch_id', 'course_id']);
            });
        }

        // Feedback release cycles table
        if (!Schema::hasTable('feedback_release_cycles')) {
            Schema::create('feedback_release_cycles', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['faculty', 'final']);
                $table->boolean('is_active')->default(false);
                $table->timestamp('released_at')->nullable();
                $table->timestamp('deadline_at')->nullable();
                $table->foreignId('released_by')->constrained('users');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('feedback_release_cycles');
        Schema::dropIfExists('final_feedback');
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('faculty_subjects');
    }
}