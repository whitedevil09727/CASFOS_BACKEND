<?php
// app/Models/Batch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'code',
        'name',
        'course_id',
        'capacity',
        'status',
        'start_date',
        'end_date',
        'lead_instructor',
        'description',
        'trainee_ids',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'trainee_ids' => 'array',
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    
    public function trainees()
    {
        return $this->belongsToMany(Trainee::class, 'batch_trainee', 'batch_id', 'trainee_id')
                    ->withTimestamps()
                    ->withPivot('enrollment_status');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
    
    public function scopePublished($query)
    {
        return $query->whereIn('status', ['Active', 'Full']);
    }
    
    // Accessors
    public function getCurrentCountAttribute(): int
    {
        return count($this->trainee_ids ?? []);
    }
    
    public function getFillPercentageAttribute(): float
    {
        if ($this->capacity <= 0) return 0;
        return min(100, ($this->current_count / $this->capacity) * 100);
    }
    
    public function getIsFullAttribute(): bool
    {
        return $this->current_count >= $this->capacity;
    }
    
    // Update status based on current enrollment
    public function updateStatusFromEnrollment(): void
    {
        if ($this->status === 'Archived') return;
        
        if ($this->current_count >= $this->capacity) {
            $this->status = 'Full';
        } elseif ($this->current_count > 0) {
            $this->status = 'Active';
        } else {
            $this->status = 'Draft';
        }
        
        $this->save();
    }
    
    // Add trainee to batch
    public function addTrainee($traineeId): void
    {
        $currentIds = $this->trainee_ids ?? [];
        if (!in_array($traineeId, $currentIds)) {
            $currentIds[] = $traineeId;
            $this->trainee_ids = $currentIds;
            $this->save();
            $this->updateStatusFromEnrollment();
        }
    }
    
    // Remove trainee from batch
    public function removeTrainee($traineeId): void
    {
        $currentIds = $this->trainee_ids ?? [];
        $this->trainee_ids = array_values(array_filter($currentIds, fn($id) => $id != $traineeId));
        $this->save();
        $this->updateStatusFromEnrollment();
    }
    
    // Bulk assign trainees
    public function assignTrainees(array $traineeIds): array
    {
        $currentIds = $this->trainee_ids ?? [];
        $newIds = array_unique(array_merge($currentIds, $traineeIds));
        $added = array_diff($newIds, $currentIds);
        
        $this->trainee_ids = $newIds;
        $this->save();
        $this->updateStatusFromEnrollment();
        
        return $added;
    }
    
    // Bulk remove trainees
    public function removeTrainees(array $traineeIds): array
    {
        $currentIds = $this->trainee_ids ?? [];
        $removed = array_intersect($currentIds, $traineeIds);
        $newIds = array_values(array_diff($currentIds, $traineeIds));
        
        $this->trainee_ids = $newIds;
        $this->save();
        $this->updateStatusFromEnrollment();
        
        return $removed;
    }
}