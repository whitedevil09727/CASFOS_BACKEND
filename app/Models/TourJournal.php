<?php
// app/Models/TourJournal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourJournal extends Model
{
    use HasFactory;
    
    protected $table = 'tour_journals';

    protected $fillable = [
        'tour_id', 'trainee_id', 'journal_link', 'remarks', 
        'status', 'submitted_at', 'approved_at', 'approved_by'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the tour this journal belongs to
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Get the trainee who submitted this journal
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    /**
     * Get the approver of this journal
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if journal is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if journal is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if journal is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    
    /**
     * Scope for pending journals
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Scope for submitted journals
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
    
    /**
     * Scope for approved journals
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}