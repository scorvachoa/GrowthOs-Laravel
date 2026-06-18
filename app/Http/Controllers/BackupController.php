<?php

namespace App\Http\Controllers;

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

        $scope = $request->query('scope', $isSuperAdmin ? 'all' : 'org_' . $user->activeOrganizationId());
        if ($scope === 'all' && !$isSuperAdmin) {
            $scope = 'org_' . $user->activeOrganizationId();
        }
        $orgId = str_starts_with($scope, 'org_') ? (int) substr($scope, 4) : null;

        $companies = $isSuperAdmin
            ? \App\Models\Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])
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

        $scope = $request->query('scope', $isSuperAdmin ? 'all' : 'org_' . $user->activeOrganizationId());

        if ($scope === 'all' && !$isSuperAdmin) {
            abort(403);
        }

        $orgId = str_starts_with($scope, 'org_') ? (int) substr($scope, 4) : null;

        $path = $this->backupService->exportToFile($orgId, $isSuperAdmin);
        $filename = 'backup_growthos_' . $scope . '_' . now()->format('Y-m-d_His') . '.json';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/json',
        ])->deleteFileAfterSend(true);
    }

    public function downloadScheduled(string $filename)
    {
        $path = BackupService::STORAGE_PATH . '/' . basename($filename);

        if (!file_exists(storage_path('app/' . $path))) {
            return back()->with('error', 'El archivo de backup no existe.');
        }

        return response()->download(storage_path('app/' . $path), $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function deleteScheduled(string $filename)
    {
        $path = BackupService::STORAGE_PATH . '/' . basename($filename);

        if (file_exists(storage_path('app/' . $path))) {
            unlink(storage_path('app/' . $path));
        }

        return back()->with('success', 'Backup eliminado.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json', 'max:51200'],
        ]);

        $contents = json_decode(file_get_contents($request->file('backup_file')->getRealPath()), true);

        if (!$contents || !isset($contents['data'])) {
            return back()->with('error', 'El archivo no tiene un formato de backup valido.');
        }

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $orgId = $user->activeOrganizationId();

        try {
            $logs = $this->backupService->import($contents['data'], $orgId, $isSuperAdmin);
            return back()->with('success', 'Restauracion completada: ' . implode(', ', $logs));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
