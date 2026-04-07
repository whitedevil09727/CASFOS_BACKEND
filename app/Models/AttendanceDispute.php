<?php
// app/Models/AttendanceDispute.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDispute extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'attendance_id',
        'user_id',
        'reason',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];
    
    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Relationships
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'Pending Review');
    }
    
    public function scopeResolved($query)
    {
        return $query->where('status', 'Resolved');
    }
}