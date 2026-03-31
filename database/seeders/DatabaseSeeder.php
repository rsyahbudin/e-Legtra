<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions first, then roles (which need permissions)
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingSeeder::class,
            DivisionSeeder::class,
            LOVSeeder::class,
            DocumentTypeSeeder::class,
            ReminderTypeSeeder::class,
            FormSectionSeeder::class,
            FormQuestionSeeder::class,
        ]);

        // Create users
        $adminRole = Role::where('ROLE_SLUG', 'super-admin')->first();
        $legalRole = Role::where('ROLE_SLUG', 'legal')->first();
        $userRole = Role::where('ROLE_SLUG', 'user')->first();

        // Get Legal division and department for default assignment
        $legalDivision = Division::where('REF_DIV_ID', 'LEGAL')->first();
        $legalDepartment = Department::where('REF_DEPT_ID', 'LEGAL')->first();

        // Admin user - assign to Legal division/dept
        $admin = User::updateOrCreate(
            ['USER_EMAIL' => 'andre@example.com'],
            [
                'USER_FULLNAME' => 'Andre User',
                'USER_ID' => '24100715',
                'USER_PASSWORD' => Hash::make('password'),
                'USER_ROLE_ID' => $adminRole->ROLE_ID,
                'DIV_ID' => $legalDivision?->LGL_ROW_ID,
                'DEPT_ID' => $legalDepartment?->LGL_ROW_ID,
            ]
        );

        // Create test user for legacy compatibility
        User::updateOrCreate(
            ['USER_EMAIL' => 'legal@example.com'],
            [
                'USER_FULLNAME' => 'Legal User',
                'USER_ID' => '12345',
                'USER_PASSWORD' => Hash::make('password'),
                'USER_ROLE_ID' => $legalRole?->ROLE_ID,
                'DIV_ID' => $legalDivision?->LGL_ROW_ID,
                'DEPT_ID' => $legalDepartment?->LGL_ROW_ID,
            ]
        );
    }
}
