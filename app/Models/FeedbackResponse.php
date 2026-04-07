<?php
// app/Models/FeedbackResponse.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    protected $table = 'feedback_responses';
    
    protected $fillable = [
        'trainee_id', 
        'faculty_id', 
        'course_id', 
        'batch_id',
        'content_relevance', 
        'structure', 
        'clarity', 
        'methodology',
        'vertical_learning', 
        'lateral_learning', 
        'overall_rating',
        'duration_rating', 
        'positive_feedback', 
        'improvement_suggestions', 
        'additional_comments', 
        'status', 
        'submitted_at'
    ];
    
    protected $casts = [
        'submitted_at' => 'datetime',
        'content_relevance' => 'integer',
        'structure' => 'integer',
        'clarity' => 'integer',
        'methodology' => 'integer',
        'vertical_learning' => 'integer',
        'lateral_learning' => 'integer',
        'overall_rating' => 'decimal:1',
    ];
    
    /**
     * Boot method to handle model events
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Calculate overall rating when ratings are updated
            $ratings = [
                $model->content_relevance,
                $model->structure,
                $model->clarity,
                $model->methodology,
                $model->vertical_learning,
                $model->lateral_learning
            ];
            
            $validRatings = array_filter($ratings);
            if (!empty($validRatings)) {
                $model->overall_rating = round(array_sum($validRatings) / count($validRatings), 1);
            }
        });
        
        static::updating(function ($model) {
            if ($model->status === 'submitted' && !$model->submitted_at) {
                $model->submitted_at = now();
            }
        });
    }
    
    /**
     * Get the trainee who submitted this feedback
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }
    
    /**
     * Get the faculty member being evaluated
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(FacultyProfile::class, 'faculty_id');
    }
    
    /**
     * Get the course being evaluated
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
    
    /**
     * Get the batch of the trainee
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
    
    /**
     * Scope for submitted feedback only
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
    
    /**
     * Scope for draft feedback only
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    /**
     * Check if feedback is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
    
    /**
     * Get rating distribution as percentage
     */
    public function getRatingDistributionAttribute()
    {
        $criteria = ['content_relevance', 'structure', 'clarity', 'methodology', 'vertical_learning', 'lateral_learning'];
        $distribution = [];
        
        foreach ($criteria as $criterion) {
            $rating = $this->$criterion;
            if ($rating) {
                $distribution[$criterion] = ($rating / 5) * 100;
            }
        }
        
        return $distribution;
    }
}