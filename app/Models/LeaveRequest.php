<?php
// app/Models/LeaveRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'leave_requests';
    
    protected $fillable = [
        'user_id',          // Required - NOT NULL
        'trainee_id',       // Optional - can be NULL
        'leave_type',
        'start_date',
        'end_date',
        'duration_days',
        'reason',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'notes',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
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
    
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    // Accessor for duration days
    public function getDurationDaysAttribute($value)
    {
        if ($value) return $value;
        
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        return $start->diffInDays($end) + 1;
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
    
    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }
    
    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }
    
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    // Boot method
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($leave) {
            if (!$leave->duration_days && $leave->start_date && $leave->end_date) {
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                $leave->duration_days = $start->diffInDays($end) + 1;
            }
        });
    }
}