<?php
// database/migrations/2024_04_05_000001_create_tour_journals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTourJournalsTable extends Migration
{
    public function up()
    {
        // Tour Journals table (links to your existing tours)
        Schema::create('tour_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');
            $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
            $table->string('journal_link')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['tour_id', 'trainee_id']);
            $table->index(['tour_id', 'status']);
            $table->index(['trainee_id', 'status']);
        });

        // Tour Enrollments (trainees assigned to tours)
        Schema::create('tour_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');
            $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
            
            $table->unique(['tour_id', 'trainee_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tour_enrollments');
        Schema::dropIfExists('tour_journals');
    }
}