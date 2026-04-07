<?php
// database/migrations/2024_03_25_000001_create_tours_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location');
            $table->date('journal_due_date');
            $table->foreignId('oic_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('gl_id')->nullable()->constrained('trainees')->onDelete('set null');
            $table->json('faculty_ids')->nullable(); // Store additional faculty IDs
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('batch_id');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('journal_due_date');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};