<?php

namespace App\Console\Commands;

use App\Models\DtrUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SyncZktecoEmployees extends Command
{
    protected $signature = 'dtr:sync-employees
        {--create-users : Also create user accounts for employees without one}';

    protected $description = 'Sync employees from ZKTeco biometric database into the DTR system';

    public function handle()
    {
        $zkEmployees = DB::connection('zkbiotime')
            ->table('personnel_employee')
            ->where('is_active', 1)
            ->get();

        $this->info("Found {$zkEmployees->count()} active employees in ZKTeco database.");

        $synced = 0;
        $skipped = 0;
        $usersCreated = 0;

        foreach ($zkEmployees as $emp) {
            $bioId = trim($emp->bio_id ?? $emp->emp_code ?? '');
            if (empty($bioId)) {
                $skipped++;
                continue;
            }

            $firstName = trim($emp->first_name ?? '');
            $lastName = trim($emp->last_name ?? '');

            if (empty($firstName) && empty($lastName)) {
                $firstName = "Employee";
                $lastName = $bioId;
            }

            DtrUser::updateOrCreate(
                ['emp_code' => $bioId],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'sex' => $emp->gender === 'M' ? 'Male' : ($emp->gender === 'F' ? 'Female' : null),
                    'employee_status' => 'Regular',
                    'is_active' => true,
                ]
            );
            $synced++;

            if ($this->option('create-users')) {
                $existingUser = User::where('emp_code', $bioId)->first();
                if (!$existingUser) {
                    $username = 'employee' . $bioId;
                    $email = $emp->email ?: $bioId . '@dtr.local';

                    User::create([
                        'name' => trim($firstName . ' ' . $lastName),
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'emp_code' => $bioId,
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'is_super' => false,
                    ]);
                    $usersCreated++;
                }
            }
        }

        $this->info("Synced {$synced} employees to dtr_users.");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} employees with missing emp_code.");
        }
        if ($this->option('create-users')) {
            $this->info("Created {$usersCreated} user accounts (default password: 'password').");
        }

        return Command::SUCCESS;
    }
}
