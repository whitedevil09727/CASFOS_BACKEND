<?php
// app/Models/Attendance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'trainee_id',
        'timetable_session_id',
        'attendance_date',
        'status',
        'marked_at',
        'remarks',
        'is_disputed',
    ];
    
    protected $casts = [
        'attendance_date' => 'date',
        'marked_at' => 'datetime',
        'is_disputed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }
    
    public function timetableSession()
    {
        return $this->belongsTo(TimetableSession::class, 'timetable_session_id');
    }
    
    public function dispute()
    {
        return $this->hasOne(AttendanceDispute::class);
    }
    
    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->where('attendance_date', $date);
    }
    
    public function scopePresent($query)
    {
        return $query->where('status', 'Present');
    }
    
    public function scopeAbsent($query)
    {
        return $query->where('status', 'Absent');
    }
    
    public function scopeNotMarked($query)
    {
        return $query->where('status', 'Not Marked');
    }
}