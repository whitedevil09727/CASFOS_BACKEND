<?php
// database/migrations/2024_01_01_000001_create_tour_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTourLinksTable extends Migration
{
    public function up()
    {
        Schema::create('tour_links', function (Blueprint $table) {
            $table->id();
            $table->string('tour_name');
            $table->string('batch_name');
            $table->string('link_id')->unique();
            $table->text('description')->nullable();
            $table->date('expiry_date');
            $table->enum('status', ['active', 'expired', 'draft'])->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('google_drive_folder_id')->nullable(); // For storing submissions
            $table->timestamps();
            
            $table->index(['status', 'expiry_date']);
            $table->index('link_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tour_links');
    }
}