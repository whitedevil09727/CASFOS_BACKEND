<?php
// app/Models/TimetableSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimetableSession extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'timetable_sessions';
    
    protected $fillable = [
        'day',
        'start_hour',
        'duration',
        'subject',
        'faculty',
        'topic',
        'room',
        'is_substituted',
        'original_faculty',
        'course_id',
        'batch_id',
    ];
    
    protected $casts = [
        'start_hour' => 'integer',
        'duration' => 'integer',
        'is_substituted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
    
    // Accessors
    public function getEndHourAttribute(): int
    {
        return $this->start_hour + $this->duration;
    }
    
    public function getTimeRangeAttribute(): string
    {
        $start = $this->formatHour($this->start_hour);
        $end = $this->formatHour($this->end_hour);
        return "{$start} – {$end}";
    }
    
    public function getFullDayAttribute(): string
    {
        $days = [
            'Mon' => 'Monday',
            'Tue' => 'Tuesday',
            'Wed' => 'Wednesday',
            'Thu' => 'Thursday',
            'Fri' => 'Friday',
        ];
        return $days[$this->day] ?? $this->day;
    }
    
    private function formatHour(int $hour): string
    {
        $period = $hour >= 12 ? 'PM' : 'AM';
        $displayHour = $hour > 12 ? $hour - 12 : $hour;
        return "{$displayHour}:00 {$period}";
    }
    
    // Check if session conflicts with another
    public function conflictsWith(TimetableSession $other): bool
    {
        if ($this->day !== $other->day) return false;
        
        $thisStart = $this->start_hour;
        $thisEnd = $this->start_hour + $this->duration;
        $otherStart = $other->start_hour;
        $otherEnd = $other->start_hour + $other->duration;
        
        return !($thisEnd <= $otherStart || $thisStart >= $otherEnd);
    }
    
    // Substitute faculty
    public function substituteFaculty(string $newFaculty): void
    {
        if (!$this->is_substituted) {
            $this->original_faculty = $this->faculty;
        }
        $this->faculty = $newFaculty;
        $this->is_substituted = true;
        $this->save();
    }
    
    // Revert substitution
    public function revertSubstitution(): void
    {
        if ($this->is_substituted && $this->original_faculty) {
            $this->faculty = $this->original_faculty;
            $this->is_substituted = false;
            $this->original_faculty = null;
            $this->save();
        }
    }
}