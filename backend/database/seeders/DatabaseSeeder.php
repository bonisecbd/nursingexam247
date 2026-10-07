<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            User::query()->updateOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => 'password',
                    'role' => 'student',
                    'is_active' => true,
                ],
            );

            User::query()->firstOrCreate(
                ['email' => 'admin@nurseexam247.test'],
                [
                    'name' => 'NurseExam Admin',
                    'password' => 'Admin@12345',
                    'role' => 'admin',
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['name' => 'Nursing', 'code' => 'NUR', 'description' => 'Nursing subjects and exam preparation.'],
            ['name' => 'General Knowledge', 'code' => 'GK', 'description' => 'General knowledge and current affairs.'],
            ['name' => 'English', 'code' => 'ENG', 'description' => 'English language and grammar.'],
            ['name' => 'Information and Communication Technology', 'code' => 'ICT', 'description' => 'Information and communication technology.'],
        ] as $subject) {
            Subject::query()->updateOrCreate(['code' => $subject['code']], $subject);
        }
    }
}
