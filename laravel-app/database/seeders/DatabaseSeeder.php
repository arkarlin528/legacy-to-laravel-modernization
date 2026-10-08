<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Only creates the back-office login. Business data (customers, orders, invoices) is not seeded:
 * it comes from the legacy database through the migrator (../migrator), exactly as in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('backoffice.admin_password');
        if (! $password) {
            $this->command?->warn('ADMIN_PASSWORD is not set; no admin user created.');

            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Demo Admin', 'password' => $password, 'email_verified_at' => now()],
        );
    }
}
