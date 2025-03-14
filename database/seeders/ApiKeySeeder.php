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
        ApiKey::create([
            'name' => 'Development API Key',
            'key' => 'FQojPlIFCVzOBZWHbVmRMMy8jkOl0XlLM67lGD2E',
            'active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
