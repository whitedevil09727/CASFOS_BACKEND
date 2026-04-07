<?php
// database/migrations/2024_03_25_000002_create_attendance_disputes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('reason');
            $table->enum('status', ['Pending Review', 'Resolved', 'Rejected'])->default('Pending Review');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('user_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('attendance_disputes');
    }
};