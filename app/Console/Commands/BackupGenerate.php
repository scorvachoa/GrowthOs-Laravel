<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupGenerate extends Command
{
    protected $signature = 'backup:generate {--org=} {--all}';
    protected $description = 'Generate a backup file and save it to storage';

    public function handle(BackupService $backupService): int
    {
        $isSuperAdmin = (bool) $this->option('all');
        $orgId = $this->option('org') ? (int) $this->option('org') : null;

        if ($isSuperAdmin && $orgId) {
            $this->error('Usa --all o --org, no ambos.');
            return Command::FAILURE;
        }

        $path = $backupService->saveToStorage($orgId, $isSuperAdmin ?: false);
        $size = filesize(storage_path('app/' . $path));

        $this->info('Backup generado: ' . $path);
        $this->info('Tamano: ' . $this->formatBytes($size));

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
