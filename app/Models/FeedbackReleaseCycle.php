<?php
// app/Models/FeedbackReleaseCycle.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackReleaseCycle extends Model
{
    protected $table = 'feedback_release_cycles';
    
    protected $fillable = [
        'type', 
        'is_active', 
        'released_at', 
        'deadline_at', 
        'released_by'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'released_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];
    
    /**
     * Get the user who released this cycle
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
    
    /**
     * Scope for active cycles only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for faculty feedback cycles
     */
    public function scopeFaculty($query)
    {
        return $query->where('type', 'faculty');
    }
    
    /**
     * Scope for final feedback cycles
     */
    public function scopeFinal($query)
    {
        return $query->where('type', 'final');
    }
    
    /**
     * Check if cycle is expired
     */
    public function isExpired(): bool
    {
        return $this->deadline_at && now()->gt($this->deadline_at);
    }
    
    /**
     * Get days remaining until deadline
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->deadline_at) return null;
        
        $days = now()->diffInDays($this->deadline_at, false);
        return $days >= 0 ? $days : 0;
    }
}