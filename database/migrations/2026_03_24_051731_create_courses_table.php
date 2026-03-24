<?php
// database/migrations/2026_03_24_051731_create_courses_table.php

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
        Schema::create('courses', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Course identifiers
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            
            // Categories as strings (validation will be handled in the controller)
            $table->string('category', 50);
            $table->string('type', 50);
            $table->string('status', 50)->default('Draft');
            
            // Date fields
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days');
            
            // Content fields
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Capacity field
            $table->integer('capacity')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('status');
            $table->index('category');
            $table->index('start_date');
            $table->index('code');
            $table->index('created_at');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};