<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $scope = $request->query('scope', $isSuperAdmin ? 'all' : 'org_'.$user->activeOrganizationId());
        if ($scope === 'all' && ! $isSuperAdmin) {
            $scope = 'org_'.$user->activeOrganizationId();
        }
        $orgId = str_starts_with($scope, 'org_') ? (int) substr($scope, 4) : null;

        $companies = $isSuperAdmin
            ? Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])
            : null;

        return Inertia::render('Backup/Index', [
            'table_sizes' => $this->backupService->getTableSizes($orgId, $isSuperAdmin),
            'is_super_admin' => $isSuperAdmin,
            'scheduled_backups' => $this->backupService->listScheduled(),
            'backup_schedule' => $this->backupService->getSchedule(),
            'companies' => $companies,
            'current_scope' => $scope,
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $scope = $request->query('scope', $isSuperAdmin ? 'all' : 'org_'.$user->activeOrganizationId());

        if ($scope === 'all' && ! $isSuperAdmin) {
            abort(403);
        }

        $orgId = str_starts_with($scope, 'org_') ? (int) substr($scope, 4) : null;

        $path = $this->backupService->exportToFile($orgId, $isSuperAdmin);
        $filename = 'backup_growthos_'.$scope.'_'.now()->format('Y-m-d_His').'.json';

        if (! file_exists($path) || filesize($path) === 0) {
            @unlink($path);

            return back()->with('error', 'Error al generar el archivo de backup.');
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/json',
        ])->deleteFileAfterSend(true);
    }

    public function downloadScheduled(string $filename)
    {
        $safeFilename = basename($filename);

        if (! preg_match('/^backup_.*\.json$/', $safeFilename)) {
            abort(404);
        }

        $this->authorizeBackupAccess($safeFilename);

        $path = realpath(storage_path('app/'.BackupService::STORAGE_PATH.'/'.$safeFilename));
        $allowedDir = realpath(storage_path('app/'.BackupService::STORAGE_PATH));

        if (! $path || ! $allowedDir || ! str_starts_with($path, $allowedDir) || ! file_exists($path)) {
            return back()->with('error', 'El archivo de backup no existe.');
        }

        return response()->download($path, $safeFilename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function deleteScheduled(string $filename)
    {
        $safeFilename = basename($filename);

        if (! preg_match('/^backup_.*\.json$/', $safeFilename)) {
            abort(404);
        }

        $this->authorizeBackupAccess($safeFilename);

        $path = BackupService::STORAGE_PATH.'/'.$safeFilename;

        if (file_exists(storage_path('app/'.$path))) {
            unlink(storage_path('app/'.$path));
        }

        return back()->with('success', 'Backup eliminado.');
    }

    /**
     * Verify the backup belongs to the user's organization.
     * Super Admin can access any backup.
     */
    private function authorizeBackupAccess(string $filename): void
    {
        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return;
        }

        $orgId = $user->activeOrganizationId();
        $filenameOrgId = $this->extractOrgIdFromFilename($filename);

        // If the backup is an "all" scope or belongs to a different org, deny access
        if ($filenameOrgId === null || (int) $filenameOrgId !== $orgId) {
            abort(403, 'No tienes permisos para acceder a este backup.');
        }
    }

    /**
     * Extract organization ID from backup filename.
     * Format: backup_growthos_org_{id}_{timestamp}.json
     * Returns null for "all" scope backups.
     */
    private function extractOrgIdFromFilename(string $filename): ?int
    {
        if (preg_match('/^backup_growthos_org_(\d+)_/', $filename, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function import(Request $request)
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json', 'max:51200'],
        ]);

        $contents = json_decode(file_get_contents($request->file('backup_file')->getRealPath()), true);

        if (! is_array($contents)) {
            return back()->with('error', 'El archivo no tiene un formato de backup válido.');
        }

        $requiredKeys = ['exported_at', 'scope', 'data'];
        $missing = array_diff($requiredKeys, array_keys($contents));
        if ($missing !== []) {
            return back()->with('error', 'El backup no contiene las keys requeridas: '.implode(', ', $missing));
        }

        if (! is_array($contents['data'])) {
            return back()->with('error', 'El campo "data" del backup debe ser un array.');
        }

        $allowedScopes = ['all', 'org'];
        if (! in_array($contents['scope'] ?? null, $allowedScopes)) {
            return back()->with('error', 'El scope del backup no es válido.');
        }

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $orgId = $user->activeOrganizationId();

        try {
            $logs = $this->backupService->import($contents['data'], $orgId, $isSuperAdmin);

            return back()->with('success', 'Restauración completada: '.implode(', ', $logs));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error al restaurar: '.$e->getMessage());
        }
    }
}
