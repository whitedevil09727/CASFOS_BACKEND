<?php
// app/Models/TourSubmission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TourSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_link_id',
        'trainee_id',
        'trainee_name',
        'roll_no',
        'tour_name',
        'journal_content',
        'file_url',
        'google_drive_file_id',
        'status',
        'admin_remarks',
        'submitted_at',
        'reviewed_at',
        'reviewed_by'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tourLink()
    {
        return $this->belongsTo(TourLink::class);
    }

    public function trainee()
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function markAsStored($googleDriveFileId = null)
    {
        $this->status = 'stored';
        if ($googleDriveFileId) {
            $this->google_drive_file_id = $googleDriveFileId;
        }
        $this->save();
    }

    public function approve($remarks = null, $reviewerId = null)
    {
        $this->status = 'approved';
        $this->admin_remarks = $remarks;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = Carbon::now();
        $this->save();
    }

    public function reject($remarks, $reviewerId = null)
    {
        $this->status = 'rejected';
        $this->admin_remarks = $remarks;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = Carbon::now();
        $this->save();
    }
}