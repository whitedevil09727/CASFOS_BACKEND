<?php
// app/Models/FacultySubject.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultySubject extends Model
{
    protected $table = 'faculty_subjects';
    
    protected $fillable = [
        'faculty_id', 'course_id', 'syllabus_status', 
        'feedback_unlocked', 'unlocked_at', 'deadline_at'
    ];
    
    protected $casts = [
        'feedback_unlocked' => 'boolean',
        'unlocked_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];
    
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(FacultyProfile::class, 'faculty_id');
    }
    
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
    
    public function getStatsAttribute()
    {
        $total = FeedbackResponse::where('faculty_id', $this->faculty_id)
            ->where('course_id', $this->course_id)
            ->count();
            
        $submitted = FeedbackResponse::where('faculty_id', $this->faculty_id)
            ->where('course_id', $this->course_id)
            ->where('status', 'submitted')
            ->count();
            
        $pending = FeedbackResponse::where('faculty_id', $this->faculty_id)
            ->where('course_id', $this->course_id)
            ->where('status', 'draft')
            ->count();
            
        return (object) [
            'total' => $total,
            'submitted' => $submitted,
            'pending' => $pending,
            'expired' => 0
        ];
    }
}