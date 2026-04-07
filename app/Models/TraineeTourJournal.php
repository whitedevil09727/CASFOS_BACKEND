<?php
// app/Models/TraineeTourJournal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TraineeTourJournal extends Model
{
    use HasFactory;

    protected $table = 'trainee_tour_journals';

    protected $fillable = [
        'tour_link_id',
        'trainee_id',
        'title',
        'content',
        'file_url',
        'file_name',
        'file_size',
        'file_type',
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

    // Make status accessible - add accessor and mutator if needed
    public function getStatusAttribute($value)
    {
        return $value;
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = $value;
    }

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

    public function markAsUploaded($fileUrl = null, $fileName = null)
    {
        $this->status = 'uploaded';
        if ($fileUrl) {
            $this->file_url = $fileUrl;
        }
        if ($fileName) {
            $this->file_name = $fileName;
        }
        $this->submitted_at = Carbon::now();
        $this->save();
    }

    public function markUnderReview()
    {
        $this->status = 'under_review';
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