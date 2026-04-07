<?php
// app/Models/TourLink.php - Simplified version

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TourLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_name',
        'batch_name',
        'link_id',
        'description',
        'expiry_date',
        'status',
        'created_by',
        'google_drive_folder_id',
        'location',      // Add this
        'duration',      // Add this
        'oic',           // Add this
        'gl',            // Add this
        'tour_date'      // Add this
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->link_id)) {
                $model->link_id = 'TOUR-' . strtoupper(Str::random(8));
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(TourSubmission::class);
    }

    public function journals()
    {
        return $this->hasMany(TraineeTourJournal::class, 'tour_link_id');
    }


    /**
     * Check if the tour link is expired
     */
    public function isExpired()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Get status attribute with automatic expiry check
     */
    public function getStatusAttribute($value)
    {
        if ($value === 'active' && $this->expiry_date && $this->expiry_date->isPast()) {
            return 'expired';
        }
        return $value;
    }

    /**
     * Scope for active links
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expiry_date', '>=', now());
    }

    /**
     * Scope for expired links
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('expiry_date', '<', now())
                ->orWhere('status', 'expired');
        });
    }
}