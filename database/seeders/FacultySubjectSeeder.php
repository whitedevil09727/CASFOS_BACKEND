<?php
// database/seeders/FacultySubjectSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FacultyProfile;
use App\Models\FacultySubject;
use App\Models\Course;

class FacultySubjectSeeder extends Seeder
{
    public function run()
    {
        $faculties = FacultyProfile::all();
        $createdCount = 0;
        
        foreach ($faculties as $faculty) {
            // Get assigned courses from JSON field
            $assignedCourses = $faculty->assigned_courses;
            
            // Handle if it's a string (JSON)
            if (is_string($assignedCourses)) {
                $assignedCourses = json_decode($assignedCourses, true);
            }
            
            // Ensure it's an array
            if (!is_array($assignedCourses)) {
                $assignedCourses = [];
            }
            
            // Create faculty_subject entries for each assigned course
            foreach ($assignedCourses as $courseId) {
                // Verify course exists
                $course = Course::find($courseId);
                if ($course) {
                    FacultySubject::updateOrCreate(
                        [
                            'faculty_id' => $faculty->id,
                            'course_id' => $courseId
                        ],
                        [
                            'syllabus_status' => 'completed', // or 'pending' based on your needs
                            'feedback_unlocked' => false,
                            'unlocked_at' => null,
                            'deadline_at' => null
                        ]
                    );
                    $createdCount++;
                    $this->command->info("Created mapping: {$faculty->id} -> Course {$courseId}");
                } else {
                    $this->command->warn("Course {$courseId} not found for faculty {$faculty->id}");
                }
            }
        }
        
        $this->command->info("Created {$createdCount} faculty-subject mappings");
    }
}