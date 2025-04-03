<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Str;

class UserSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        // Create an admin user
        User::create([
            'name' => 'Max Mustermann1',
            'display_name' => 'Maxi1',
            'email' => 'max@example1.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create a system user for deleted accounts
        User::create([
            'name' => 'System Deleted User',
            'display_name' => 'Deleted User',
            'email' => 'deleted@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);


        // Create a moderator user
        User::create([
            'name' => 'Max Mustermann2',
            'display_name' => 'Maxi2',
            'email' => 'max@example2.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'moderator',
            'email_verified_at' => now(),
        ]);

        // Create multiple regular users with incrementing names/emails
        for ($i = 3; $i <= 10; $i++) {
            User::create([
                'name' => "Max Mustermann{$i}",
                'display_name' => "Maxi{$i}",
                'email' => "max@example{$i}.com",
                'password' => Hash::make('sicheresPasswort123'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]);
        }
    }
}
