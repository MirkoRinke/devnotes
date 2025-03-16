<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ForbiddenName;

class ForbiddenNameSeeder extends Seeder {
    /**
     * Files to import names from with matching type
     */
    private $files = [
        'app/partial_matches.txt' => 'partial',
        'app/exact_matches.txt' => 'exact',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void {
        $totalImported = 0;

        foreach ($this->files as $file => $matchType) {
            $this->command->info("Importing from: {$file} (match_type: {$matchType})");
            $count = $this->importFromFile(storage_path($file), $matchType);
            $totalImported += $count;
        }

        $this->command->info("Import completed. Total names imported: $totalImported");
    }

    /**
     * Import names from a file
     */
    private function importFromFile(string $filePath, string $matchType): int {
        if (!file_exists($filePath)) {
            $this->command->warn("The file $filePath does not exist.");
            return 0;
        }

        $names = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;

        foreach ($names as $name) {
            $name = trim($name);
            if (!empty($name)) {
                ForbiddenName::firstOrCreate(['name' => $name], ['match_type' => $matchType]);
                $count++;

                if ($count % 100 === 0) {
                    $this->command->info("$count Names imported from current file.");
                }
            }
        }

        $this->command->info("File import successful: $count Names imported from $filePath.");
        return $count;
    }
}
