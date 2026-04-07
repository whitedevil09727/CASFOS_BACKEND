<?php
// database/migrations/2024_03_25_000002_create_leave_balances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trainee_id')->nullable()->constrained('trainees')->onDelete('cascade');
            $table->integer('total_days')->default(12);
            $table->integer('used_days')->default(0);
            $table->integer('pending_days')->default(0);
            // Remove the virtual column - we'll calculate remaining days in the model
            $table->year('year')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'year']);
            $table->index('user_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};