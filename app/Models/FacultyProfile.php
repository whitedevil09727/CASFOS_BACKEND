<?php
// app/Models/FacultyProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;


class FacultyProfile extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'designation',
        'speciality',
        'station',
        'department',
        'phone',
        'assigned_courses',
        'status',
    ];
    
    protected $casts = [
        'assigned_courses' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    // In app/Models/FacultyProfile.php
// public function user()
// {
//     return $this->belongsTo(User::class, 'user_id');
// }
    
    // Get assigned courses as collection
    public function getAssignedCoursesAttribute($value)
    {
        $courseIds = json_decode($value, true) ?? [];
        return Course::whereIn('id', $courseIds)->get();
    }
    
    // Get course IDs only
    public function getAssignedCourseIdsAttribute()
    {
        return json_decode($this->attributes['assigned_courses'] ?? '[]', true);
    }

     public function feedbackResponses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class, 'faculty_id');
    }
    
    // Set assigned courses
    public function setAssignedCoursesAttribute($value)
    {
        $this->attributes['assigned_courses'] = json_encode($value);
    }
}