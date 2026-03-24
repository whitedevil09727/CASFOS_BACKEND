<?php
// app/Models/Trainee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trainee extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'roll_number',
        'name',
        'gender',
        'service_type',
        'enrollment_status',
        'email',
        'phone',
        'date_of_birth',
        'address',
    ];
    
    protected $casts = [
        'date_of_birth' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function batches()
    {
        return $this->belongsToMany(Batch::class, 'batch_trainee', 'trainee_id', 'batch_id')
                    ->withTimestamps()
                    ->withPivot('enrollment_status');
    }
    
    // Scopes
    public function scopeEnrolled($query)
    {
        return $query->where('enrollment_status', 'Enrolled');
    }
    
    public function scopeByServiceType($query, $serviceType)
    {
        if ($serviceType !== 'All') {
            return $query->where('service_type', $serviceType);
        }
        return $query;
    }
    
    public function scopeByGender($query, $gender)
    {
        if ($gender !== 'All') {
            return $query->where('gender', $gender);
        }
        return $query;
    }
}