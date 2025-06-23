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

            $user->id = $data['id'] ?? null;
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
        $this->command->info('Seeding users...');

        $multiplier = (int) 10;

        $userCount = (int) 50;


        // Create an admin user
        $this->createUserWithProfile([
            'id' => 1,
            'name' => 'Admin',
            'display_name' => 'Admin',
            'email' => 'max@example1.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created successfully!');

        // Create a system user for deleted accounts
        $this->createUserWithProfile([
            'id' => 2,
            'name' => 'System',
            'display_name' => 'System',
            'email' => 'system@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);

        $this->command->info('System user created successfully!');

        // Create a system user for deleted accounts
        $this->createUserWithProfile([
            'id' => 3,
            'name' => 'System Deleted User',
            'display_name' => 'Deleted User',
            'email' => 'deleted@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);

        $this->command->info('System deleted user created successfully!');

        $this->createUserWithProfile([
            'id' => 4,
            'name' => 'Guest Report',
            'display_name' => 'Guest Report',
            'email' => 'guestreport@system.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'system',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Guest report user created successfully!');

        // Create a moderator user
        $this->createUserWithProfile([
            'id' => 5,
            'name' => 'Moderator',
            'display_name' => 'Moderator',
            'email' => 'max@example4.com',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'moderator',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Moderator user created successfully!');


        // Create multiple regular users with incrementing names/emails
        for ($i = 20; $i <= 30; $i++) {
            $this->createUserWithProfile([
                'id' => $i,
                'name' => "Max Mustermann{$i}",
                'display_name' => "Maxi{$i}",
                'email' => "max@example{$i}.com",
                'password' => Hash::make('sicheresPasswort123'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('Multiple custom regular users created successfully!');

        // Create a guest user
        $this->createUserWithProfile([
            'id' => 101,
            'name' => 'Guest',
            'display_name' => 'Guest',
            'email' => 'guest@system.local',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'user',
            'email_verified_at' => now(),
            'account_purpose' => 'guest',
        ]);

        $this->command->info('Guest user created successfully!');

        // Create a specified number of users
        for ($i = 0; $i < $multiplier; $i++) {
            $user =  User::factory($userCount)->create();

            foreach ($user as $createdUser) {
                $this->userRelationService->createUserProfile($createdUser);
                $this->userRelationService->checkUsername($createdUser);
            }
            $this->command->info("Created " . ($userCount * $multiplier) . " users. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }
    }
}
