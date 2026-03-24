<?php
// database/migrations/2024_03_24_000003_create_trainees_table_simple.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainees', function (Blueprint $table) {
            $table->id();
            $table->string('roll_number', 50)->unique();
            $table->string('name', 255);
            $table->enum('gender', ['Male', 'Female', 'Other'])->default('Male');
            $table->enum('service_type', ['IFS', 'SFS', 'Other'])->default('Other');
            $table->enum('enrollment_status', ['Enrolled', 'Pending', 'Withdrawn', 'All'])->default('Pending');
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('roll_number');
            $table->index('service_type');
            $table->index('enrollment_status');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('trainees');
    }
};