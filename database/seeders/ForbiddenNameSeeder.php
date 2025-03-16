<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ForbiddenName;
use Carbon\Carbon;

class ForbiddenNameSeeder extends Seeder {
    /**
     * Files to import names from
     * 
     * @var array
     */
    private $files = [
        'app/forbidden_names.txt',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void {
        $totalImported = 0;

        foreach ($this->files as $file) {
            $this->command->info("Importing from: " . $file);
            $count = $this->importFromFile(storage_path($file));
            $totalImported += $count;
        }

        $this->command->info("Import completed. Total names imported: $totalImported");
    }

    /**
     * Import names from a file
     * 
     * @param string $filePath Path to the file
     * @return int Number of imported names
     */
    private function importFromFile(string $filePath): int {
        if (!file_exists($filePath)) {
            $this->command->warn("The file $filePath does not exist.");
            return 0;
        }

        // Read the names from the file and import them into the database
        $names = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;

        foreach ($names as $name) {
            $name = trim($name);
            if (!empty($name)) {
                ForbiddenName::firstOrCreate(['name' => $name]);
                $count++;

                // Display progress
                if ($count % 100 === 0) {
                    $this->command->info("$count Names imported from current file.");
                }
            }
        }

        $this->command->info("File import successful: $count Names imported from $filePath.");
        return $count;
    }
}
