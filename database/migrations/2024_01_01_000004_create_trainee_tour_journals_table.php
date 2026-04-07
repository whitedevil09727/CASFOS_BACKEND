<?php
// database/migrations/2024_01_01_000004_create_trainee_tour_journals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTraineeTourJournalsTable extends Migration
{
    public function up()
    {
        Schema::create('trainee_tour_journals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_link_id');
            $table->unsignedBigInteger('trainee_id');
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
            
            $table->foreign('tour_link_id')->references('id')->on('tour_links')->onDelete('cascade');
            $table->foreign('trainee_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['tour_link_id', 'trainee_id']);
            $table->index(['trainee_id', 'status']);
            $table->index('tour_link_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('trainee_tour_journals');
    }
}