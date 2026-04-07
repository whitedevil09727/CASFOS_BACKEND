<?php
// app/Models/TourEnrollment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourEnrollment extends Model
{
    protected $table = 'tour_enrollments';

    protected $fillable = [
        'tour_id', 'trainee_id', 'is_mandatory'
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    /**
     * Get the tour this enrollment belongs to
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Get the trainee this enrollment belongs to
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }
}