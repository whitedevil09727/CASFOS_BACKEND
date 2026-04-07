<?php
// app/Models/FinalFeedback.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalFeedback extends Model
{
    protected $table = 'final_feedback';
    
    protected $fillable = [
        'trainee_id', 
        'batch_id', 
        'course_id',
        'overall_rating', 
        'course_content', 
        'teaching_quality',
        'infrastructure', 
        'placement_support',
        'strengths', 
        'areas_for_improvement', 
        'recommendations',
        'status', 
        'submitted_at'
    ];
    
    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_rating' => 'integer',
        'course_content' => 'integer',
        'teaching_quality' => 'integer',
        'infrastructure' => 'integer',
        'placement_support' => 'integer',
    ];
    
    /**
     * Get the trainee who submitted this feedback
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }
    
    /**
     * Get the batch for this feedback
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
    
    /**
     * Get the course being evaluated
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
    
    /**
     * Scope for submitted feedback only
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
    
    /**
     * Calculate completion percentage
     */
    public function getCompletionPercentageAttribute()
    {
        $totalFields = 8; // total number of feedback fields
        $filledFields = 0;
        
        $fields = [
            $this->overall_rating,
            $this->course_content,
            $this->teaching_quality,
            $this->infrastructure,
            $this->placement_support,
            $this->strengths,
            $this->areas_for_improvement,
            $this->recommendations
        ];
        
        foreach ($fields as $field) {
            if (!is_null($field) && $field !== '') {
                $filledFields++;
            }
        }
        
        return round(($filledFields / $totalFields) * 100);
    }
}