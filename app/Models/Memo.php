<?php
// app/Models/Memo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Memo extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'memo_number',
        'trainee_id',
        'trainee_name',
        'trainee_roll_no',
        'batch_name',
        'course_name',
        'date',
        'absent_sessions',
        'status',
        'approved_by',
        'approved_by_name',
        'approved_at',
        'rejection_reason',
        'generated_at'
    ];

    protected $casts = [
        'absent_sessions' => 'array',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function trainee()
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    // Accessors - Fix the date formatting
    public function getFormattedDateAttribute(): string
    {
        return $this->date instanceof Carbon 
            ? $this->date->format('M d, Y') 
            : Carbon::parse($this->date)->format('M d, Y');
    }
    
    public function getFormattedGeneratedAtAttribute(): string
    {
        return $this->generated_at instanceof Carbon 
            ? $this->generated_at->format('M d, Y h:i A') 
            : Carbon::parse($this->generated_at)->format('M d, Y h:i A');
    }
    
    public function getFormattedApprovedAtAttribute(): ?string
    {
        if (!$this->approved_at) return null;
        
        return $this->approved_at instanceof Carbon 
            ? $this->approved_at->format('M d, Y h:i A') 
            : Carbon::parse($this->approved_at)->format('M d, Y h:i A');
    }
    
    public function getAbsentSessionsCountAttribute(): int
    {
        return count($this->absent_sessions ?? []);
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }
    
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
    
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }
    
    public function scopeForTrainee($query, $traineeId)
    {
        return $query->where('trainee_id', $traineeId);
    }
}