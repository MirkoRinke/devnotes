<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\PostAllowedValue;
use App\Models\UserProfile;

class UserProfileFavoriteLanguageSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->assignFavoriteLanguagesToUserProfiles();
    }

    /**
     * Assign random favorite languages to all user profiles
     */
    protected function assignFavoriteLanguagesToUserProfiles(): void {
        $languageIds = PostAllowedValue::where('type', 'language')->pluck('id')->toArray();

        foreach (UserProfile::all() as $profile) {
            $favoriteLanguages = (array) array_rand(array_flip($languageIds), rand(1, min(5, count($languageIds))));
            $profile->favoriteLanguages()->sync($favoriteLanguages);
        }

        $this->command->info('Favorite languages assigned to all user profiles!');
    }
}
