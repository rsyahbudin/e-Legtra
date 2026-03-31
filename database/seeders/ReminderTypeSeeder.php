<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReminderTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ReminderType::updateOrCreate(
            ['REF_REMIND_TYPE_ID' => 'email'],
            [
                'REF_REMIND_TYPE_NAME' => 'Email Notification',
                'REF_REMIND_TYPE_IS_ACTIVE' => true,
            ]
        );
    }
}
