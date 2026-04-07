<?php
// database/seeders/TimetableSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TimetableSession;
use Carbon\Carbon;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = [
            [
                'day' => 'Mon',
                'start_hour' => 8,
                'duration' => 2,
                'subject' => 'Forest Ecology',
                'faculty' => 'Dr. Rajesh Kumar',
                'topic' => 'Ecosystem Dynamics',
                'room' => 'Lecture Hall A',
            ],
            [
                'day' => 'Mon',
                'start_hour' => 11,
                'duration' => 1,
                'subject' => 'GIS & Remote Sensing',
                'faculty' => 'Prof. Venkatesh Rao',
                'topic' => 'Introduction to GIS',
                'room' => 'Lab 2',
            ],
            [
                'day' => 'Mon',
                'start_hour' => 14,
                'duration' => 2,
                'subject' => 'Silviculture',
                'faculty' => 'Dr. Anita Menon',
                'topic' => 'Forest Regeneration Methods',
                'room' => 'Lecture Hall B',
            ],
            [
                'day' => 'Tue',
                'start_hour' => 8,
                'duration' => 1,
                'subject' => 'Physical Training',
                'faculty' => 'Prof. Suresh Nair',
                'room' => 'Ground',
            ],
            [
                'day' => 'Tue',
                'start_hour' => 10,
                'duration' => 2,
                'subject' => 'Wildlife Management',
                'faculty' => 'Dr. Priya Sharma',
                'topic' => 'Habitat Assessment',
                'room' => 'Lecture Hall A',
            ],
            [
                'day' => 'Tue',
                'start_hour' => 14,
                'duration' => 2,
                'subject' => 'Forest Laws & Policy',
                'faculty' => 'Prof. Kavitha Iyer',
                'topic' => 'Wildlife Protection Act',
                'room' => 'Lecture Hall C',
            ],
            [
                'day' => 'Wed',
                'start_hour' => 9,
                'duration' => 2,
                'subject' => 'Biodiversity Conservation',
                'faculty' => 'Dr. Mahesh Patel',
                'topic' => 'Protected Area Management',
                'room' => 'Lecture Hall A',
            ],
            [
                'day' => 'Wed',
                'start_hour' => 14,
                'duration' => 1,
                'subject' => 'Environmental Impact Assessment',
                'faculty' => 'Dr. Arun Krishnamurthy',
                'room' => 'Lecture Hall B',
            ],
            [
                'day' => 'Thu',
                'start_hour' => 8,
                'duration' => 2,
                'subject' => 'Forest Working Plan',
                'faculty' => 'Prof. Deepa Thomas',
                'topic' => 'Working Plan Preparation',
                'room' => 'Lecture Hall A',
            ],
            [
                'day' => 'Thu',
                'start_hour' => 11,
                'duration' => 1,
                'subject' => 'Carbon Sequestration',
                'faculty' => 'Dr. Sanjay Gupta',
                'topic' => 'Carbon Credits & REDD+',
                'room' => 'Lecture Hall C',
            ],
            [
                'day' => 'Thu',
                'start_hour' => 14,
                'duration' => 2,
                'subject' => 'Agroforestry',
                'faculty' => 'Dr. Rajesh Kumar',
                'topic' => 'Farm Forestry Systems',
                'room' => 'Field',
            ],
            [
                'day' => 'Fri',
                'start_hour' => 8,
                'duration' => 1,
                'subject' => 'Physical Training',
                'faculty' => 'Prof. Suresh Nair',
                'room' => 'Ground',
            ],
            [
                'day' => 'Fri',
                'start_hour' => 10,
                'duration' => 2,
                'subject' => 'Field Exercises',
                'faculty' => 'Dr. Priya Sharma',
                'topic' => 'Field Survey Techniques',
                'room' => 'Field',
            ],
            [
                'day' => 'Fri',
                'start_hour' => 14,
                'duration' => 2,
                'subject' => 'GIS & Remote Sensing',
                'faculty' => 'Prof. Venkatesh Rao',
                'topic' => 'Satellite Image Analysis',
                'room' => 'Lab 2',
            ],
        ];
        
        foreach ($sessions as $session) {
            TimetableSession::create($session);
        }
        
        $this->command->info('Timetable sessions seeded successfully!');
    }
}