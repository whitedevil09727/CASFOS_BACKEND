<?php
// app/Models/TourItinerary.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourItinerary extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'tour_id',
        'day_number',
        'date',
        'location',
        'activities',
        'accommodation',
        'notes',
    ];
    
    protected $casts = [
        'date' => 'date',
    ];
    
    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}