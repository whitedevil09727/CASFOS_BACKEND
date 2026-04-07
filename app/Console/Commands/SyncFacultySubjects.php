<?php
// app/Console/Commands/SyncFacultySubjects.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FacultyProfile;
use App\Models\FacultySubject;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

class SyncFacultySubjects extends Command
{
    protected $signature = 'feedback:sync-faculty-subjects';
    protected $description = 'Sync faculty subjects from faculty_profiles assigned_courses JSON field';

    public function handle()
    {
        $faculties = FacultyProfile::all();
        $created = 0;
        $errors = 0;
        
        foreach ($faculties as $faculty) {
            $this->info("Processing faculty ID: {$faculty->id}");
            
            // Get assigned courses
            $assignedCourses = $faculty->assigned_courses;
            
            // Handle different data types
            if (is_string($assignedCourses)) {
                $assignedCourses = json_decode($assignedCourses, true);
            }
            
            // Ensure it's an array
            if (!is_array($assignedCourses)) {
                $assignedCourses = [];
            }
            
            if (empty($assignedCourses)) {
                $this->warn("No assigned courses for faculty ID: {$faculty->id}");
                continue;
            }
            
            // Extract course IDs from the objects
            $courseIds = [];
            foreach ($assignedCourses as $course) {
                if (is_array($course) && isset($course['id'])) {
                    $courseIds[] = $course['id'];
                } elseif (is_numeric($course)) {
                    $courseIds[] = $course;
                }
            }
            
            $this->info("Found course IDs: " . json_encode($courseIds));
            
            foreach ($courseIds as $courseId) {
                // Verify course exists
                $course = Course::find($courseId);
                if ($course) {
                    try {
                        $result = FacultySubject::updateOrCreate(
                            [
                                'faculty_id' => $faculty->id,
                                'course_id' => $courseId
                            ],
                            [
                                'syllabus_status' => 'completed',
                                'feedback_unlocked' => false
                            ]
                        );
                        
                        if ($result->wasRecentlyCreated) {
                            $created++;
                            $this->info("✓ Created: Faculty {$faculty->id} -> Course {$course->name} (ID: {$courseId})");
                        } else {
                            $this->info("Already exists: Faculty {$faculty->id} -> Course {$course->name}");
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $this->error("Error creating mapping: " . $e->getMessage());
                    }
                } else {
                    $this->warn("Course ID {$courseId} not found for faculty {$faculty->id}");
                    $errors++;
                }
            }
        }
        
        $this->info("\n=== SYNC COMPLETED ===");
        $this->info("Created: {$created}");
        $this->info("Errors: {$errors}");
        $this->info("Total faculty subjects: " . FacultySubject::count());
    }
}