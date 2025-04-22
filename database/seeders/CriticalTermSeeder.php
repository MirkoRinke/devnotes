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
        foreach ($this->criticalTermsEN as $name => $severity) {
            CriticalTerm::create([
                'name' => $name,
                'language' => 'en',
                'severity' => $severity,
            ]);
        }

        foreach ($this->criticalTermsDE as $name => $severity) {
            CriticalTerm::create([
                'name' => $name,
                'language' => 'de',
                'severity' => $severity,
            ]);
        }
    }
}
