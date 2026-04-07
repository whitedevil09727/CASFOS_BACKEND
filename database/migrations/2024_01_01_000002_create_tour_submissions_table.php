<?php
// database/migrations/2024_01_01_000002_create_tour_submissions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTourSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('tour_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_link_id')->constrained()->onDelete('cascade');
            $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
            $table->string('trainee_name');
            $table->string('roll_no');
            $table->string('tour_name');
            $table->text('journal_content')->nullable();
            $table->string('file_url')->nullable();
            $table->string('google_drive_file_id')->nullable();
            $table->enum('status', ['pending', 'stored', 'approved', 'rejected'])->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index(['tour_link_id', 'status']);
            $table->index('trainee_id');
            $table->unique(['tour_link_id', 'trainee_id']); // Prevent duplicate submissions
        });
    }

    public function down()
    {
        Schema::dropIfExists('tour_submissions');
    }
}