<?php
// database/seeders/CourseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use Carbon\Carbon;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'code' => 'IFS-2026-01',
                'name' => 'IFS Foundation Course',
                'category' => 'Induction',
                'type' => 'Residential',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-31',
                'status' => 'Published',
                'description' => 'Core foundation training for newly recruited Indian Forest Service officers.',
                'capacity' => 60,
                'notes' => 'Requires physical fitness certificate',
            ],
            [
                'code' => 'SFS-2026-02',
                'name' => 'State Forest Service Induction',
                'category' => 'Induction',
                'type' => 'Residential',
                'start_date' => '2026-09-15',
                'end_date' => '2027-03-15',
                'status' => 'Under Review',
                'description' => 'Induction training covering core forestry, legal frameworks, and physical fitness.',
                'capacity' => 40,
            ],
            [
                'code' => 'GIS-ADV-26',
                'name' => 'Advanced GIS & Remote Sensing',
                'category' => 'In-Service',
                'type' => 'Hybrid',
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-20',
                'status' => 'Draft',
                'description' => 'Specialized training on QGIS, ArcGIS, and satellite imagery analysis for working professionals.',
                'capacity' => 30,
            ],
            [
                'code' => 'WL-MGT-01',
                'name' => 'Wildlife Crime Intelligence',
                'category' => 'Special',
                'type' => 'Non-Residential',
                'start_date' => '2026-05-05',
                'end_date' => '2026-05-10',
                'status' => 'Published',
                'description' => 'Short-term workshop focused on intelligence gathering and anti-poaching operations.',
                'capacity' => 25,
            ],
            [
                'code' => 'CLIMATE-25',
                'name' => 'Carbon Sequestration Workshop',
                'category' => 'In-Service',
                'type' => 'Hybrid',
                'start_date' => '2025-11-01',
                'end_date' => '2025-11-05',
                'status' => 'Archived',
                'description' => 'Past workshop on climate change mitigation strategies.',
                'capacity' => 50,
            ],
        ];
        
        foreach ($courses as $course) {
            // Calculate duration days
            $startDate = Carbon::parse($course['start_date']);
            $endDate = Carbon::parse($course['end_date']);
            $course['duration_days'] = $startDate->diffInDays($endDate) + 1;
            
            Course::create($course);
        }
        
        $this->command->info('Courses seeded successfully!');
    }
}