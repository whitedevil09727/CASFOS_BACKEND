<?php
// database/migrations/2024_03_26_000001_add_monitoring_indexes_to_attendances.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check and add indexes only if they don't exist
        if (Schema::hasTable('attendances')) {
            // Add indexes for better performance
            Schema::table('attendances', function (Blueprint $table) {
                // Check if index doesn't exist before creating
                if (!Schema::hasIndex('attendances', ['user_id', 'attendance_date'])) {
                    $table->index(['user_id', 'attendance_date']);
                }
                
                if (!Schema::hasIndex('attendances', ['trainee_id', 'attendance_date'])) {
                    $table->index(['trainee_id', 'attendance_date']);
                }
                
                if (!Schema::hasIndex('attendances', ['status'])) {
                    $table->index('status');
                }
            });
        }
        
        // Add computed column for attendance percentage (if using PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('attendances')) {
            // Check if the computed column already exists
            $columnExists = DB::select("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'attendances' 
                AND column_name = 'attendance_percentage'
            ");
            
            if (empty($columnExists)) {
                // This is a more complex operation - you might want to handle it differently
                // For now, we'll skip as it's not critical for functionality
                // DB::statement('...');
            }
        }
    }
    
    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'attendance_date']);
                $table->dropIndex(['trainee_id', 'attendance_date']);
                $table->dropIndex(['status']);
            });
        }
    }
};