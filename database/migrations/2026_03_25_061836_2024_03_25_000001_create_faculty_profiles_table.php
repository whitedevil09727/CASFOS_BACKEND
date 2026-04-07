<?php
// database/migrations/2024_03_25_000001_create_faculty_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('designation');
            $table->string('speciality');
            $table->string('station');
            $table->string('department');
            $table->string('phone')->nullable();
            $table->json('assigned_courses')->nullable(); // Store assigned course IDs
            $table->enum('status', ['Active', 'Visiting', 'On Leave', 'Pending Review'])->default('Active');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('faculty_profiles');
    }
};