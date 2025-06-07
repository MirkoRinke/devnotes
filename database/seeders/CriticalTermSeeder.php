<?php

namespace Database\Seeders;

use App\Models\CriticalTerm;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CriticalTermSeeder extends Seeder {

    /**
     * The critical terms in English
     *
     * @var array
     */
    protected $criticalTermsEN =
    [
        'fraud' => 3,
        'malware' => 4,
        'phishing' => 4,
        'scam' => 3,
        'virus' => 3,
        'worm'  => 3,
        'rootkit' => 3,
    ];

    /**
     * The critical terms in German
     *
     * @var array
     */
    protected $criticalTermsDE =
    [
        'betrug' => 3,
        'schadsoftware' => 4,
        'betrugsversuch' => 3,
        'wurm'  => 3,
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void {
        $this->command->info('Seeding critical terms...');

        foreach ($this->criticalTermsEN as $name => $severity) {
            CriticalTerm::firstOrCreate(
                ['name' => $name],
                ['language' => 'en', 'severity' => $severity, 'created_by_role' => 'system', 'created_by_user_id' => 2]
            );
        }
        $this->command->info('Critical terms in English have been seeded.');

        foreach ($this->criticalTermsDE as $name => $severity) {
            CriticalTerm::firstOrCreate(
                ['name' => $name],
                ['language' => 'de', 'severity' => $severity, 'created_by_role' => 'system', 'created_by_user_id' => 2]
            );
        }

        $this->command->info('Critical terms in German have been seeded.');
    }
}
