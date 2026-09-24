<?php

namespace Database\Seeders;

use App\Models\RewardCategory;
use Illuminate\Database\Seeder;

class RewardCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'reward_name' => 'Employee of the Month',
                'short_code' => 'EOM',
                'status' => 1,
                'details' => 'Monthly outstanding performance and commitment award nominated by departmental leads.',
            ],
            [
                'reward_name' => 'Spot Recognition Award',
                'short_code' => 'SPOT',
                'status' => 1,
                'details' => 'Immediate recognition for going above and beyond on critical tasks and operational excellence.',
            ],
            [
                'reward_name' => 'Excellence in Innovation',
                'short_code' => 'INNOVATION',
                'status' => 1,
                'details' => 'Awarded for exceptional innovative ideas, process enhancements, or technological breakthroughs.',
            ],
            [
                'reward_name' => 'Project Milestone Bonus',
                'short_code' => 'MILESTONE',
                'status' => 1,
                'details' => 'Reward incentive for timely and successful delivery of high-impact strategic projects.',
            ],
            [
                'reward_name' => 'Customer Delight Award',
                'short_code' => 'DELIGHT',
                'status' => 1,
                'details' => 'Recognition for exemplary client service and exceptional positive client reviews.',
            ],
        ];

        foreach ($categories as $cat) {
            RewardCategory::query()->updateOrCreate(
                ['short_code' => $cat['short_code']],
                $cat
            );
        }
    }
}
