<?php
// database/migrations/2024_03_24_000002_create_batches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->integer('capacity');
            $table->string('status')->default('Draft');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('lead_instructor')->nullable();
            $table->text('description')->nullable();
            $table->json('trainee_ids')->nullable(); // Store as JSON array
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('course_id');
            $table->index('start_date');
        });
        
        // Add check constraint for status
        DB::statement("ALTER TABLE batches ADD CONSTRAINT batches_status_check CHECK (status IN ('Draft', 'Active', 'Full', 'Archived'))");
    }
    
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};