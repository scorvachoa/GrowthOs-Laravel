<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\DayObservation;
use App\Models\ExtraTask;
use App\Models\GeneratedVideo;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VideoTask;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupService
{
    const SCOPE_ALL = 'all';
    const SCOPE_ORG = 'org';
    const CHUNK_SIZE = 500;
    const STORAGE_PATH = 'backups';

    public function saveToStorage(?int $orgId = null, bool $isSuperAdmin = false): string
    {
        $path = $this->exportToFile($orgId, $isSuperAdmin);
        $suffix = $isSuperAdmin ? 'all' : 'org_' . $orgId;
        $filename = 'backup_' . $suffix . '_' . now()->format('Y-m-d_His') . '.json';
        $dest = self::STORAGE_PATH . '/' . $filename;

        if (!is_dir(storage_path('app/' . self::STORAGE_PATH))) {
            mkdir(storage_path('app/' . self::STORAGE_PATH), 0755, true);
        }

        copy($path, storage_path('app/' . $dest));
        unlink($path);

        return $dest;
    }

    public function listScheduled(): array
    {
        $dir = storage_path('app/' . self::STORAGE_PATH);
        if (!is_dir($dir)) return [];

        $files = glob($dir . '/*.json');
        rsort($files);

        return array_map(fn ($f) => [
            'filename' => basename($f),
            'size' => filesize($f),
            'size_formatted' => $this->formatBytes(filesize($f)),
            'created_at' => date('Y-m-d H:i:s', filemtime($f)),
        ], $files);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function exportToFile(?int $orgId = null, bool $isSuperAdmin = false): string
    {
        $scope = $isSuperAdmin ? self::SCOPE_ALL : self::SCOPE_ORG;
        $path = tempnam(sys_get_temp_dir(), 'backup_');

        $handle = fopen($path, 'w');
        fwrite($handle, '{');
        $this->jsonProp($handle, 'exported_at', now()->toIso8601String(), false);
        fwrite($handle, ',');
        $this->jsonProp($handle, 'app_version', '1.0');
        fwrite($handle, ',');
        $this->jsonProp($handle, 'scope', $scope);
        fwrite($handle, ',');
        $this->jsonProp($handle, 'organization_id', $scope === self::SCOPE_ORG ? $orgId : null);
        fwrite($handle, ',');
        fwrite($handle, '"data":{');

        if ($scope === self::SCOPE_ALL) {
            $this->writeTable($handle, 'organizations', Organization::query());
            $this->writeTable($handle, 'users', User::query());
            $this->writeTable($handle, 'roles', Role::query());
            $this->writeTable($handle, 'permissions', Permission::query());
            $this->writePivot($handle, 'role_has_permissions', DB::table('role_has_permissions'), 'role_id');
            $this->writePivot($handle, 'model_has_roles', DB::table('model_has_roles'), 'role_id');
            $this->writePivot($handle, 'model_has_permissions', DB::table('model_has_permissions'), 'permission_id');
            $this->writeTable($handle, 'channels', Channel::query());
            $this->writeTable($handle, 'video_tasks', VideoTask::withTrashed());
            $this->writeTable($handle, 'extra_tasks', ExtraTask::withTrashed());
            $this->writeTable($handle, 'ideas', Idea::query());
            $this->writeTable($handle, 'generated_videos', GeneratedVideo::query());
            $this->writeTable($handle, 'report_histories', ReportHistory::query());
            $this->writeTable($handle, 'day_observations', DayObservation::query());
            $this->writeTable($handle, 'vacations', Vacation::query());
        } else {
            $orgRoleIds = Role::where('organization_id', $orgId)->pluck('id');
            $allRoleIds = $orgRoleIds->merge(Role::whereNull('organization_id')->pluck('id'));

            $org = Organization::find($orgId);
            $this->rawProp($handle, 'organization', $org ? $org->toJson() : 'null');
            $this->writeTable($handle, 'users', User::where('organization_id', $orgId));
            $this->writeTable($handle, 'roles', Role::whereIn('id', $allRoleIds));
            $this->writeTable($handle, 'permissions', Permission::query());
            $this->writePivot($handle, 'role_has_permissions', DB::table('role_has_permissions')->whereIn('role_id', $allRoleIds), 'role_id');
            $this->writePivot($handle, 'model_has_roles', DB::table('model_has_roles')->whereIn('role_id', $orgRoleIds)->orWhereNull('role_id'), 'role_id');
            $this->writeTable($handle, 'channels', Channel::where('organization_id', $orgId));
            $this->writeTable($handle, 'video_tasks', VideoTask::withTrashed()->where('organization_id', $orgId));
            $this->writeTable($handle, 'extra_tasks', ExtraTask::withTrashed()->where('organization_id', $orgId));
            $this->writeTable($handle, 'ideas', Idea::where('organization_id', $orgId));
            $this->writeTable($handle, 'generated_videos', GeneratedVideo::where('organization_id', $orgId));
            $this->writeTable($handle, 'report_histories', ReportHistory::where('organization_id', $orgId));
            $this->writeTable($handle, 'day_observations', DayObservation::where('organization_id', $orgId));
            $this->writeTable($handle, 'vacations', Vacation::whereHas('user', fn($q) => $q->where('organization_id', $orgId)));
        }

        fwrite($handle, '}');
        fwrite($handle, '}');
        fclose($handle);

        return $path;
    }

    private function writeTable($handle, string $key, $query, string $orderBy = 'id'): void
    {
        fwrite($handle, '"' . $key . '":[');
        $first = true;
        $query->orderBy($orderBy)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$first) {
            foreach ($rows as $row) {
                if (!$first) fwrite($handle, ',');
                fwrite($handle, $row->toJson());
                $first = false;
            }
        });
        fwrite($handle, '],');
    }

    private function writePivot($handle, string $key, $query, string $orderBy): void
    {
        fwrite($handle, '"' . $key . '":[');
        $first = true;
        $query->orderBy($orderBy)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$first) {
            foreach ($rows as $row) {
                if (!$first) fwrite($handle, ',');
                fwrite($handle, json_encode((array) $row));
                $first = false;
            }
        });
        fwrite($handle, '],');
    }

    private function jsonProp($handle, string $key, mixed $value, bool $leadingComma = true): void
    {
        if ($leadingComma) fwrite($handle, ',');
        fwrite($handle, '"' . $key . '":' . json_encode($value));
    }

    private function rawProp($handle, string $key, string $json): void
    {
        fwrite($handle, '"' . $key . '":' . $json . ',');
    }

    public function export(?int $orgId = null, bool $isSuperAdmin = false): array
    {
        $scope = $isSuperAdmin ? self::SCOPE_ALL : self::SCOPE_ORG;

        $data = [
            'exported_at' => now()->toIso8601String(),
            'app_version' => '1.0',
            'scope' => $scope,
            'organization_id' => $scope === self::SCOPE_ORG ? $orgId : null,
            'data' => [],
        ];

        if ($scope === self::SCOPE_ALL) {
            $data['data'] = [
                'organizations' => Organization::all()->toArray(),
                'users' => User::all()->toArray(),
                'roles' => Role::all()->toArray(),
                'permissions' => Permission::all()->toArray(),
                'role_has_permissions' => DB::table('role_has_permissions')->get()->toArray(),
                'model_has_roles' => DB::table('model_has_roles')->get()->toArray(),
                'model_has_permissions' => DB::table('model_has_permissions')->get()->toArray(),
                'channels' => Channel::all()->toArray(),
                'video_tasks' => VideoTask::withTrashed()->get()->toArray(),
                'extra_tasks' => ExtraTask::withTrashed()->get()->toArray(),
                'ideas' => Idea::all()->toArray(),
                'generated_videos' => GeneratedVideo::all()->toArray(),
                'report_histories' => ReportHistory::all()->toArray(),
                'day_observations' => DayObservation::all()->toArray(),
                'vacations' => Vacation::all()->toArray(),
            ];
        } else {
            $orgRoleIds = Role::where('organization_id', $orgId)->pluck('id');
            $allRoleIds = $orgRoleIds->merge(Role::whereNull('organization_id')->pluck('id'));

            $data['data'] = [
                'organization' => Organization::find($orgId)?->toArray(),
                'users' => User::where('organization_id', $orgId)->get()->toArray(),
                'roles' => Role::whereIn('id', $allRoleIds)->get()->toArray(),
                'permissions' => Permission::all()->toArray(),
                'role_has_permissions' => DB::table('role_has_permissions')
                    ->whereIn('role_id', $allRoleIds)->get()->toArray(),
                'model_has_roles' => DB::table('model_has_roles')
                    ->whereIn('role_id', $orgRoleIds)->orWhereNull('role_id')->get()->toArray(),
                'channels' => Channel::where('organization_id', $orgId)->get()->toArray(),
                'video_tasks' => VideoTask::withTrashed()->where('organization_id', $orgId)->get()->toArray(),
                'extra_tasks' => ExtraTask::withTrashed()->where('organization_id', $orgId)->get()->toArray(),
                'ideas' => Idea::where('organization_id', $orgId)->get()->toArray(),
                'generated_videos' => GeneratedVideo::where('organization_id', $orgId)->get()->toArray(),
                'report_histories' => ReportHistory::where('organization_id', $orgId)->get()->toArray(),
                'day_observations' => DayObservation::where('organization_id', $orgId)->get()->toArray(),
                'vacations' => Vacation::whereHas('user', fn($q) => $q->where('organization_id', $orgId))->get()->toArray(),
            ];
        }

        return $data;
    }

    public function import(array $data, ?int $userOrgId = null, bool $isSuperAdmin = false): array
    {
        $logs = [];

        DB::transaction(function () use ($data, $userOrgId, $isSuperAdmin, &$logs) {
            $scope = $data['scope'] ?? self::SCOPE_ALL;
            $backupOrgId = $data['organization_id'] ?? null;

            if (!$isSuperAdmin) {
                if ($scope !== self::SCOPE_ORG || $backupOrgId !== $userOrgId) {
                    throw new \RuntimeException('Solo puedes restaurar backups de tu propia empresa.');
                }
            }

            if ($scope === self::SCOPE_ALL) {
                $tables = [
                    'organizations' => Organization::class,
                    'roles' => Role::class,
                    'permissions' => Permission::class,
                ];

                foreach ($tables as $key => $modelClass) {
                    if (!isset($data[$key])) continue;
                    foreach ($data[$key] as $row) {
                        $modelClass::updateOrCreate(['id' => $row['id']], $row);
                    }
                    $logs[] = count($data[$key]) . " registros en {$key}";
                }

                $pivotTables = ['role_has_permissions', 'model_has_roles', 'model_has_permissions'];
                foreach ($pivotTables as $table) {
                    if (!isset($data[$table])) continue;
                    foreach ($data[$table] as $row) {
                        DB::table($table)->updateOrInsert((array) $row);
                    }
                    $logs[] = count($data[$table]) . " registros en {$table}";
                }

                $scopedTables = [
                    'users' => User::class,
                    'channels' => Channel::class,
                    'video_tasks' => VideoTask::class,
                    'extra_tasks' => ExtraTask::class,
                    'ideas' => Idea::class,
                    'generated_videos' => GeneratedVideo::class,
                    'report_histories' => ReportHistory::class,
                    'day_observations' => DayObservation::class,
                    'vacations' => Vacation::class,
                ];

                foreach ($scopedTables as $key => $modelClass) {
                    if (!isset($data[$key])) continue;
                    foreach ($data[$key] as $row) {
                        $modelClass::withTrashed()->updateOrCreate(['id' => $row['id']], $row);
                    }
                    $logs[] = count($data[$key]) . " registros en {$key}";
                }
            } else {
                if (isset($data['organization'])) {
                    Organization::updateOrCreate(['id' => $data['organization']['id']], $data['organization']);
                    $logs[] = "1 registro en organization";
                }

                if (isset($data['roles'])) {
                    foreach ($data['roles'] as $row) {
                        Role::updateOrCreate(['id' => $row['id']], $row);
                    }
                    $logs[] = count($data['roles']) . " registros en roles";
                }

                if (isset($data['permissions'])) {
                    foreach ($data['permissions'] as $row) {
                        Permission::updateOrCreate(['id' => $row['id']], $row);
                    }
                    $logs[] = count($data['permissions']) . " registros en permissions";
                }

                $orgPivots = ['role_has_permissions', 'model_has_roles'];
                foreach ($orgPivots as $table) {
                    if (!isset($data[$table])) continue;
                    foreach ($data[$table] as $row) {
                        DB::table($table)->updateOrInsert((array) $row);
                    }
                    $logs[] = count($data[$table]) . " registros en {$table}";
                }

                $scopedTables = [
                    'users' => User::class,
                    'channels' => Channel::class,
                    'video_tasks' => VideoTask::class,
                    'extra_tasks' => ExtraTask::class,
                    'ideas' => Idea::class,
                    'generated_videos' => GeneratedVideo::class,
                    'report_histories' => ReportHistory::class,
                    'day_observations' => DayObservation::class,
                    'vacations' => Vacation::class,
                ];

                foreach ($scopedTables as $key => $modelClass) {
                    if (!isset($data[$key])) continue;
                    foreach ($data[$key] as $row) {
                        $modelClass::withTrashed()->updateOrCreate(['id' => $row['id']], $row);
                    }
                    $logs[] = count($data[$key]) . " registros en {$key}";
                }
            }
        });

        return $logs;
    }

    public function getTableSizes(?int $orgId = null, bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            return [
                'organizations' => Organization::count(),
                'users' => User::count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'channels' => Channel::count(),
                'video_tasks' => VideoTask::count(),
                'extra_tasks' => ExtraTask::count(),
                'ideas' => Idea::count(),
                'generated_videos' => GeneratedVideo::count(),
                'report_histories' => ReportHistory::count(),
                'day_observations' => DayObservation::count(),
                'vacations' => Vacation::count(),
            ];
        }

        return [
            'users' => User::where('organization_id', $orgId)->count(),
            'channels' => Channel::where('organization_id', $orgId)->count(),
            'video_tasks' => VideoTask::where('organization_id', $orgId)->count(),
            'extra_tasks' => ExtraTask::where('organization_id', $orgId)->count(),
            'ideas' => Idea::where('organization_id', $orgId)->count(),
            'generated_videos' => GeneratedVideo::where('organization_id', $orgId)->count(),
            'report_histories' => ReportHistory::where('organization_id', $orgId)->count(),
            'day_observations' => DayObservation::where('organization_id', $orgId)->count(),
            'vacations' => Vacation::whereHas('user', fn($q) => $q->where('organization_id', $orgId))->count(),
        ];
    }

    const SCHEDULE_FILE = 'settings' . DIRECTORY_SEPARATOR . 'backup_schedule.json';

    public function getSchedule(): array
    {
        $path = storage_path('app/' . self::SCHEDULE_FILE);
        if (!file_exists($path)) {
            return ['time' => '03:00', 'day' => 'sunday'];
        }
        return json_decode(file_get_contents($path), true);
    }

    public function setSchedule(string $time, string $day): void
    {
        $dir = dirname(storage_path('app/' . self::SCHEDULE_FILE));
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            storage_path('app/' . self::SCHEDULE_FILE),
            json_encode(['time' => $time, 'day' => $day, 'updated_at' => now()->toIso8601String()], JSON_PRETTY_PRINT)
        );
    }

    public function getScheduleCron(): string
    {
        $s = $this->getSchedule();
        [$h, $m] = explode(':', $s['time']);

        $dayMap = [
            'monday' => '1', 'tuesday' => '2', 'wednesday' => '3',
            'thursday' => '4', 'friday' => '5', 'saturday' => '6', 'sunday' => '0',
        ];

        return $m . ' ' . $h . ' * * ' . ($dayMap[$s['day']] ?? '0');
    }
}
