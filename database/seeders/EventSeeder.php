<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            // Manual event types
            [
                'event_code' => 'MEETING',
                'event_name' => 'Meetings',
                'event_source' => 'manual',
                'priority' => 10,
                'status' => 1,
            ],
            [
                'event_code' => 'TRAINING',
                'event_name' => 'Training',
                'event_source' => 'manual',
                'priority' => 20,
                'status' => 1,
            ],
            [
                'event_code' => 'WORKSHOP',
                'event_name' => 'Workshops',
                'event_source' => 'manual',
                'priority' => 30,
                'status' => 1,
            ],
            [
                'event_code' => 'COMPANY_EVENT',
                'event_name' => 'Company Events',
                'event_source' => 'manual',
                'priority' => 40,
                'status' => 1,
            ],
            [
                'event_code' => 'HR_ANNOUNCEMENT',
                'event_name' => 'HR Announcements',
                'event_source' => 'manual',
                'priority' => 50,
                'status' => 1,
            ],
            [
                'event_code' => 'TEAM_ACTIVITY',
                'event_name' => 'Team Activities',
                'event_source' => 'manual',
                'priority' => 60,
                'status' => 1,
            ],
            [
                'event_code' => 'TASK_DEADLINE',
                'event_name' => 'Tasks & Deadlines',
                'event_source' => 'manual',
                'priority' => 70,
                'status' => 1,
            ],
            // System event types
            [
                'event_code' => 'BIRTHDAY',
                'event_name' => 'Birthday',
                'event_source' => 'system',
                'priority' => 10,
                'status' => 1,
            ],
            [
                'event_code' => 'WORK_ANNIVERSARY',
                'event_name' => 'Work Anniversary',
                'event_source' => 'system',
                'priority' => 20,
                'status' => 1,
            ],
            [
                'event_code' => 'NEW_JOINER',
                'event_name' => 'New Joiners',
                'event_source' => 'system',
                'priority' => 30,
                'status' => 1,
            ],
            [
                'event_code' => 'EMPLOYEE_ON_LEAVE',
                'event_name' => 'Employees on Leave',
                'event_source' => 'system',
                'priority' => 40,
                'status' => 1,
            ],
            [
                'event_code' => 'BUSINESS_TRAVEL',
                'event_name' => 'Business Travel',
                'event_source' => 'system',
                'priority' => 50,
                'status' => 1,
            ],
        ];

        foreach ($types as $typeData) {
            EventType::query()->updateOrCreate(
                ['event_code' => $typeData['event_code']],
                $typeData
            );
        }

        $companyEventType = EventType::query()->where('event_code', 'COMPANY_EVENT')->first();
        $meetingType = EventType::query()->where('event_code', 'MEETING')->first();
        $trainingType = EventType::query()->where('event_code', 'TRAINING')->first();

        // Sample Today's events
        if ($companyEventType) {
            Event::query()->updateOrCreate(
                ['title' => 'Friday lunch'],
                [
                    'event_type_id' => $companyEventType->id,
                    'description' => 'Weekly team company lunch at main dining hall.',
                    'start_datetime' => now()->setTime(13, 0),
                    'end_datetime' => now()->setTime(14, 30),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );
        }

        if ($meetingType) {
            Event::query()->updateOrCreate(
                ['title' => 'Alex from Davi'],
                [
                    'event_type_id' => $meetingType->id,
                    'description' => 'Supplier discussion with Alex representing Davi machinery.',
                    'start_datetime' => now()->setTime(15, 0),
                    'end_datetime' => now()->setTime(16, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );
        }

        if ($trainingType) {
            Event::query()->updateOrCreate(
                ['title' => 'Cobot Training'],
                [
                    'event_type_id' => $trainingType->id,
                    'description' => 'Hands-on Cobot operating & safety workshop.',
                    'start_datetime' => now()->setTime(10, 0),
                    'end_datetime' => now()->setTime(12, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );
        }

        // Sample Upcoming expos / events
        if ($companyEventType) {
            Event::query()->updateOrCreate(
                ['title' => 'Gulf Cleaning Expo'],
                [
                    'event_type_id' => $companyEventType->id,
                    'description' => 'Middle East premier industrial cleaning and machinery expo.',
                    'start_datetime' => now()->addDays(5)->setTime(9, 0),
                    'end_datetime' => now()->addDays(7)->setTime(18, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );

            Event::query()->updateOrCreate(
                ['title' => 'Ajban Expo'],
                [
                    'event_type_id' => $companyEventType->id,
                    'description' => 'Annual equipment exhibition in Abu Dhabi.',
                    'start_datetime' => now()->addDays(12)->setTime(10, 0),
                    'end_datetime' => now()->addDays(14)->setTime(19, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );
        }
    }
}
