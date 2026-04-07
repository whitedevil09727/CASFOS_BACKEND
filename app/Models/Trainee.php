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
        'user_id',
        'batch_id', // Make sure this exists
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
    
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

        public function getRollNumberAttribute($value)
    {
        return $value ?? 'N/A';
    }
}