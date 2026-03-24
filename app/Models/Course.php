<?php
// app/Models/Course.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory;
    // use SoftDeletes; // Uncomment if you want soft deletes
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'category',
        'type',
        'start_date',
        'end_date',
        'duration_days',
        'status',
        'description',
        'capacity',
        'notes',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_days' => 'integer',
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calculate duration when saving
        static::saving(function ($course) {
            if ($course->start_date && $course->end_date) {
                $course->duration_days = $course->start_date->diffInDays($course->end_date) + 1;
            }
        });
    }
}