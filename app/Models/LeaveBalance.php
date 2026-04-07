<?php
// app/Models/LeaveBalance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LeaveBalance extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'trainee_id',
        'total_days',
        'used_days',
        'pending_days',
        'year',
    ];
    
    protected $casts = [
        'total_days' => 'integer',
        'used_days' => 'integer',
        'pending_days' => 'integer',
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }
    
    // Accessor for remaining days
    public function getRemainingDaysAttribute()
    {
        return $this->total_days - $this->used_days - $this->pending_days;
    }
    
    // Helper methods
    public function addUsedDays($days)
    {
        $this->used_days += $days;
        $this->save();
    }
    
    public function addPendingDays($days)
    {
        $this->pending_days += $days;
        $this->save();
    }
    
    public function removePendingDays($days)
    {
        $this->pending_days -= $days;
        $this->save();
    }
    
    // Get or create balance for user - ONLY for trainees
//     public static function getOrCreate($userId, $year = null)
//     {
//         $year = $year ?? Carbon::now()->year;
        
//         // First, check if user exists and is a trainee
//         $user = User::find($userId);
        
//         // If user is not a trainee, return a default balance (for admins viewing)
//         if (!$user || $user->role !== 'trainee') {
//             return (object) [
//                 'total_days' => 0,
//                 'used_days' => 0,
//                 'pending_days' => 0,
//                 'remaining_days' => 0,
//                 'save' => function() {}, // Dummy save method
//                 'addPendingDays' => function($days) {},
//                 'addUsedDays' => function($days) {},
//                 'removePendingDays' => function($days) {},
//             ];
//         }
        
//         // Get trainee for this user
//         $trainee = Trainee::where('user_id', $userId)->first();
        
//         // If no trainee profile exists, don't create one automatically
//         if (!$trainee) {
//             \Log::warning('Trainee profile not found for user', ['user_id' => $userId]);
//             return (object) [
//                 'total_days' => 0,
//                 'used_days' => 0,
//                 'pending_days' => 0,
//                 'remaining_days' => 0,
//                 'save' => function() {},
//                 'addPendingDays' => function($days) {},
//                 'addUsedDays' => function($days) {},
//                 'removePendingDays' => function($days) {},
//             ];
//         }
        
//         // Find existing balance or create new one
//         $balance = self::where('user_id', $userId)
//             ->where('year', $year)
//             ->first();
            
//         if (!$balance) {
//             $balance = self::create([
//                 'user_id' => $userId,
//                 'trainee_id' => $trainee->id,
//                 'total_days' => 12,
//                 'used_days' => 0,
//                 'pending_days' => 0,
//                 'year' => $year,
//             ]);
//         }
        
//         return $balance;
//     }
// }

public static function getOrCreate($userId, $year = null)
{
    $year = $year ?? Carbon::now()->year;
    
    // First, check if user exists
    $user = User::find($userId);
    
    // If user doesn't exist, return dummy object
    if (!$user) {
        return (object) [
            'total_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 0,
            'save' => function() {},
            'addPendingDays' => function($days) {},
            'addUsedDays' => function($days) {},
            'removePendingDays' => function($days) {},
        ];
    }
    
    // If user is not a trainee, return dummy object
    if ($user->role !== 'trainee') {
        return (object) [
            'total_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 0,
            'save' => function() {},
            'addPendingDays' => function($days) {},
            'addUsedDays' => function($days) {},
            'removePendingDays' => function($days) {},
        ];
    }
    
    // Get trainee for this user
    $trainee = Trainee::where('user_id', $userId)->first();
    
    // If no trainee profile exists, return dummy object
    if (!$trainee) {
        return (object) [
            'total_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 0,
            'save' => function() {},
            'addPendingDays' => function($days) {},
            'addUsedDays' => function($days) {},
            'removePendingDays' => function($days) {},
        ];
    }
    
    // Find existing balance or create new one
    $balance = self::where('user_id', $userId)
        ->where('year', $year)
        ->first();
        
    if (!$balance) {
        $balance = self::create([
            'user_id' => $userId,
            'trainee_id' => $trainee->id,
            'total_days' => 12,
            'used_days' => 0,
            'pending_days' => 0,
            'year' => $year,
        ]);
    }
    
    return $balance;
}
}