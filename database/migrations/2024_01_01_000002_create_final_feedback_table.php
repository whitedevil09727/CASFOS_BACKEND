<?php
// database/migrations/2024_01_01_000002_create_final_feedback_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinalFeedbackTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('final_feedback')) {
            Schema::create('final_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainee_id')->constrained('users')->onDelete('cascade');
                $table->string('assignment_id')->nullable();
                $table->json('responses')->nullable();
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                
                $table->index('trainee_id');
                $table->index('status');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('final_feedback');
    }
}