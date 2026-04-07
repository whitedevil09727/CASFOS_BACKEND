<?php
// app/Models/Batch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'code',
        'name',
        'course_id',
        'capacity',
        'status',
        'start_date',
        'end_date',
        'lead_instructor',
        'description',
        'trainee_ids',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'trainee_ids' => 'array',
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    
    public function trainees()
    {
        // If you have a direct relationship through trainee_ids
        if ($this->trainee_ids) {
            return Trainee::whereIn('id', $this->trainee_ids);
        }
        return collect();
    }
    

      /**
     * Get users (trainees) in this batch
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'batch_user', 'batch_id', 'user_id');
    }

    /**
     * Get feedback responses for this batch
     */
    public function feedbackResponses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class, 'batch_id');
    }

    /**
     * Get final feedback for this batch
     */
    public function finalFeedback(): HasMany
    {
        return $this->hasMany(FinalFeedback::class, 'batch_id');
    }


    // Get trainee count
    public function getTraineesCountAttribute()
    {
        return $this->trainee_ids ? count($this->trainee_ids) : 0;
    }
    
    // Get current count of trainees
    public function getCurrentCountAttribute(): int
    {
        return $this->trainee_ids ? count($this->trainee_ids) : 0;
    }
    
    // Get fill percentage
    public function getFillPercentageAttribute(): float
    {
        if ($this->capacity <= 0) return 0;
        return min(100, ($this->current_count / $this->capacity) * 100);
    }
    
    // Check if batch is full
    public function getIsFullAttribute(): bool
    {
        return $this->current_count >= $this->capacity;
    }

     /**
     * Get the tours for this batch
     */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'batch_id');
    }

    /**
     * Get the trainees in this batch
     */
    // public function trainees(): BelongsToMany
    // {
    //     return $this->belongsToMany(User::class, 'batch_user', 'batch_id', 'user_id')
    //         ->where('role', 'trainee');
    // }

    /**
     * Get the courses for this batch
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'batch_courses', 'batch_id', 'course_id');
    }
}