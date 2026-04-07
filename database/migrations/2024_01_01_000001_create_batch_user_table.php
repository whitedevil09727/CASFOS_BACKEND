<?php
// database/migrations/2024_01_01_000001_create_batch_user_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBatchUserTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('batch_user')) {
            Schema::create('batch_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['batch_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('batch_user');
    }
}