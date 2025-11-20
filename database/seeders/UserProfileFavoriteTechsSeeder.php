<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\PostAllowedValue;
use App\Models\UserProfile;

class UserProfileFavoriteTechsSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->assignFavoriteTechsToUserProfiles();
    }

    /**
     * Assign random favorite technologies to all user profiles
     */
    protected function assignFavoriteTechsToUserProfiles(): void {
        $technologyIds = PostAllowedValue::whereIn('type', ['language', 'technology'])->pluck('id')->toArray();

        foreach (UserProfile::all() as $profile) {
            $favoriteTechs = (array) array_rand(array_flip($technologyIds), rand(1, min(5, count($technologyIds))));
            $profile->favoriteTechs()->sync($favoriteTechs);
        }

        $this->command->info('Favorite languages assigned to all user profiles!');
    }
}
