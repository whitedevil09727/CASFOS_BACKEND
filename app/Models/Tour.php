<?php
// app/Models/Tour.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Tour extends Model
{
    use SoftDeletes;
    
    protected $table = 'tours';
    
    protected $fillable = [
        'code',
        'name',
        'batch_id',
        'start_date',
        'end_date',
        'location',
        'journal_due_date',
        'oic_id',
        'gl_id',
        'faculty_ids',
        'description',
    ];
    
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'journal_due_date' => 'datetime',
        'faculty_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    /**
     * Get the batch that this tour belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
    
    /**
     * Get the Officer In-Charge (faculty)
     */
    public function oic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oic_id');
    }
    
    /**
     * Get the Group Leader (trainee)
     */
    public function groupLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gl_id');
    }
    
    /**
     * Get the itineraries for this tour
     */
    public function itineraries(): HasMany
    {
        return $this->hasMany(TourItinerary::class);
    }
    
    /**
     * Get the tour journals for this tour
     */
    public function journals(): HasMany
    {
        return $this->hasMany(TourJournal::class);
    }
    
    /**
     * Get trainees enrolled in this tour
     */
    public function enrolledTrainees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tour_enrollments', 'tour_id', 'trainee_id')
            ->where('role', 'trainee');
    }
    
    /**
     * Get duration in days
     */
    public function getDurationDaysAttribute(): int
    {
        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);
        $end = $this->end_date instanceof Carbon ? $this->end_date : Carbon::parse($this->end_date);
        
        return $start->diffInDays($end) + 1;
    }
    
    /**
     * Get tour status
     */
    public function getStatusAttribute(): string
    {
        $today = Carbon::today();
        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);
        $end = $this->end_date instanceof Carbon ? $this->end_date : Carbon::parse($this->end_date);
        
        if ($today < $start) {
            return 'Upcoming';
        } elseif ($today > $end) {
            return 'Completed';
        } else {
            return 'In Progress';
        }
    }
    
    /**
     * Get submitted journals count
     */
    public function getSubmittedCountAttribute(): int
    {
        return $this->journals()->where('status', 'submitted')->count();
    }
    
    /**
     * Get pending journals count
     */
    public function getPendingCountAttribute(): int
    {
        return $this->journals()->where('status', 'pending')->count();
    }
    
    /**
     * Get approved journals count
     */
    public function getApprovedCountAttribute(): int
    {
        return $this->journals()->where('status', 'approved')->count();
    }
    
    /**
     * Get completion rate percentage
     */
    public function getCompletionRateAttribute(): float
    {
        $total = $this->journals()->count();
        if ($total === 0) return 0;
        $submitted = $this->submitted_count;
        return round(($submitted / $total) * 100, 1);
    }
    
    /**
     * Scope for upcoming tours
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', Carbon::today());
    }
    
    /**
     * Scope for in-progress tours
     */
    public function scopeInProgress($query)
    {
        return $query->where('start_date', '<=', Carbon::today())
                     ->where('end_date', '>=', Carbon::today());
    }
    
    /**
     * Scope for completed tours
     */
    public function scopeCompleted($query)
    {
        return $query->where('end_date', '<', Carbon::today());
    }
    
    /**
     * Boot method for auto-generating code
     */
    // protected static function boot()
    // {
    //     parent::boot();
        
    //     static::creating(function ($tour) {
    //         if (!$tour->code) {
    //             $batch = Batch::find($tour->batch_id);
    //             if ($batch) {
    //                 $count = self::where('batch_id', $tour->batch_id)->count() + 1;
    //                 $tour->code = strtoupper(substr($batch->name, 0, 3)) . "-TOUR-" . str_pad($count, 2, '0', STR_PAD_LEFT);
    //             } else {
    //                 $tour->code = 'TOUR-' . str_pad(self::count() + 1, 4, '0', STR_PAD_LEFT);
    //             }
    //         }
    //     });
    // }/
    protected static function boot()
{
    parent::boot();
    
    static::creating(function ($tour) {
        if (!$tour->code) {
            $tour->code = self::generateUniqueCode($tour->batch_id);
        }
    });
}

/**
 * Generate a unique tour code
 */
public static function generateUniqueCode($batchId): string
{
    $batch = Batch::find($batchId);
    $batchPrefix = $batch ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $batch->name), 0, 3)) : 'TOUR';
    
    $maxAttempts = 10;
    for ($i = 1; $i <= $maxAttempts; $i++) {
        // Get the next number for this batch
        $count = self::where('batch_id', $batchId)->count();
        $nextNumber = $count + $i;
        $code = $batchPrefix . "-TOUR-" . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        
        // Check if code exists
        if (!self::where('code', $code)->exists()) {
            return $code;
        }
    }
    
    // Fallback with timestamp
    return $batchPrefix . "-TOUR-" . time();
}
}