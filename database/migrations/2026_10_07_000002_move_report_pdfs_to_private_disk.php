<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Los PDF de reportes dejan de vivir en el disco público (web-servible)
     * y pasan al disco privado `local` (storage/app/private/reports).
     */
    public function up(): void
    {
        $this->moveReports(storage_path('app/public/reports'), storage_path('app/private/reports'));
    }

    public function down(): void
    {
        $this->moveReports(storage_path('app/private/reports'), storage_path('app/public/reports'));
    }

    private function moveReports(string $from, string $to): void
    {
        if (! is_dir($from)) {
            return;
        }

        File::ensureDirectoryExists($to);
        File::moveDirectory($from, $to, true);
    }
};
