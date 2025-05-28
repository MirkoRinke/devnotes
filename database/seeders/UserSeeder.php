<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Services\UserRelationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder {

    /**
     *  The Service used in the Seeder
     */
    protected $userRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(UserRelationService $userRelationService) {
        $this->userRelationService = $userRelationService;
    }

    /**
     * Create a user with profile and perform moderation checks
     *
     * @param array $data
     * @return User
     * 
     * @example | $this->createUserWithProfile($data);
     */
    private function createUserWithProfile(array $data): User {
        return DB::transaction(function () use ($data) {
            $user = new User();

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $data['password'];
            $user->display_name = $data['display_name'] ?? null;
            $user->role = $data['role'] ?? 'user';
            $user->email_verified_at = $data['email_verified_at'] ?? null;
            $user->account_purpose = $data['account_purpose'] ?? 'regular';

            $user->save();

            $this->userRelationService->createUserProfile($user);
            $this->userRelationService->checkUsername($user);

            return $user;
        });
    }

    /**
     * Run the database seeds.
     */
    public function run(): void {
        // Create an admin user
        $this->createUserWithProfile([
            'name' => 'Max Mustermann1',
            'display_name' => 'admin',
            'email' => 'max@example1.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create a system user for deleted accounts
        $this->createUserWithProfile([
            'name' => 'System',
            'display_name' => 'System',
            'email' => 'system@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);

        // Create a system user for deleted accounts
        $this->createUserWithProfile([
            'name' => 'System Deleted User',
            'display_name' => 'Deleted User',
            'email' => 'deleted@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);

        // Create a moderator user
        $this->createUserWithProfile([
            'name' => 'Max Mustermann4',
            'display_name' => 'Maxi4',
            'email' => 'max@example4.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'moderator',
            'email_verified_at' => now(),
        ]);

        // Create multiple regular users with incrementing names/emails
        for ($i = 5; $i <= 10; $i++) {
            $this->createUserWithProfile([
                'name' => "Max Mustermann{$i}",
                'display_name' => "Maxi{$i}",
                'email' => "max@example{$i}.com",
                'password' => Hash::make('sicheresPasswort123'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]);
        }

        // Create a guest user
        $this->createUserWithProfile([
            'name' => 'Guest',
            'display_name' => 'Guest',
            'email' => 'guest@system.local',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'user',
            'email_verified_at' => now(),
            'account_purpose' => 'guest',
        ]);
    }
}
