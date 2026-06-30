<?php

namespace Database\Seeders;

use App\Models\DtrUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperUserSeeder extends Seeder
{
    public function run()
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@dtr.com'],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'emp_code' => 'ADMIN',
                'username' => 'superadmin',
                'office' => 'Admin Office',
                'section' => 'Administration',
                'password' => Hash::make('superadmin123'),
                'is_super' => true,
            ]
        );

        DtrUser::updateOrCreate(
            ['emp_code' => 'ADMIN'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'department' => 'Administration',
                'office' => 'Admin Office',
                'section' => 'Administration',
                'employee_status' => 'Regular',
                'is_active' => true,
            ]
        );

        $this->command->info('Super user created: superadmin@dtr.com / superadmin123');
    }
}
