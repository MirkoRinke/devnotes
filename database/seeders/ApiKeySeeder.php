<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\ApiKey;

use Illuminate\Support\Str;
use Carbon\Carbon;

class ApiKeySeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->command->info('Seeding API keys...');

        $apiKey = new ApiKey();
        $apiKey->name = 'Development API Key';
        $apiKey->key = 'FQojPlIFCVzOBZWHbVmRMMy8jkOl0XlLM67lGD2E';
        $apiKey->active = true;
        $apiKey->save();

        $this->command->info('API keys seeded successfully!');
    }
}
