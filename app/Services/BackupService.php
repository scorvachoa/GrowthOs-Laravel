<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\DayObservation;
use App\Models\ExtraTask;
use App\Models\GeneratedVideo;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VideoTask;
use App\Models\WorkSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupService
{
    const SCOPE_ALL = 'all';

    const SCOPE_ORG = 'org';

    const CHUNK_SIZE = 500;

    const STORAGE_PATH = 'backups';

    private const ALLOWED_COLUMNS = [
        'organizations' => ['id', 'name', 'logo_path', 'primary_color', 'admin_invite_code', 'invite_code'],
        'users' => ['id', 'name', 'email', 'password', 'organization_id', 'settings', 'email_verified_at'],
        'channels' => ['id', 'name', 'color', 'youtube_channel_id', 'channel_url', 'organization_id'],
        'video_tasks' => ['id', 'title', 'task_date', 'time_range', 'script', 'copy', 'youtube_url', 'status', 'channel_id', 'created_by', 'key_phrases', 'translations', 'organization_id', 'is_pending'],
        'extra_tasks' => ['id', 'title', 'description', 'task_date', 'time_range', 'status', 'location', 'created_by', 'organization_id'],
        'ideas' => ['id', 'channel_id', 'content', 'is_used', 'tags', 'priority', 'category', 'created_by', 'organization_id'],
        'generated_videos' => ['id', 'idea', 'script', 'copy_es', 'copy_en', 'copy_pt', 'copy_fr', 'copy_de', 'copy_it', 'copy_ja', 'copy_ko', 'copy_zh', 'audio_path', 'video_path', 'used_in_planner', 'user_id', 'organization_id'],
        'report_histories' => ['id', 'scope', 'filename', 'filters_json', 'user_id', 'organization_id'],
        'day_observations' => ['id', 'date', 'observation', 'organization_id'],
        'vacations' => ['id', 'user_id', 'start_date', 'end_date', 'status'],
        'work_sessions' => ['id', 'video_task_id', 'date', 'time_range', 'status', 'organization_id'],
        'time_offs' => ['id', 'user_id', 'date', 'type', 'status'],
    ];

    public function saveToStorage(?int $orgId = null, bool $isSuperAdmin = false): string
    {
        $path = $this->exportToFile($orgId, $isSuperAdmin);
        $suffix = $isSuperAdmin ? 'all' : 'org_'.$orgId;
        $filename = 'backup_'.$suffix.'_'.now()->format('Y-m-d_His').'.json';
        $dest = self::STORAGE_PATH.'/'.$filename;

        if (! is_dir(storage_path('app/'.self::STORAGE_PATH))) {
            mkdir(storage_path('app/'.self::STORAGE_PATH), 0755, true);
        }

        copy($path, storage_path('app/'.$dest));
        unlink($path);

        return $dest;
    }

    public function listScheduled(): array
    {
        $dir = storage_path('app/'.self::STORAGE_PATH);
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.json');
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

        return round($bytes, 1).' '.$units[$i];
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

        $firstDataKey = true;

        if ($scope === self::SCOPE_ALL) {
            $firstDataKey = $this->writeTable($handle, 'organizations', Organization::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'users', User::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'roles', Role::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'permissions', Permission::query(), $firstDataKey);
            $firstDataKey = $this->writePivot($handle, 'role_has_permissions', DB::table('role_has_permissions'), 'role_id', $firstDataKey);
            $firstDataKey = $this->writePivot($handle, 'model_has_roles', DB::table('model_has_roles'), 'role_id', $firstDataKey);
            $firstDataKey = $this->writePivot($handle, 'model_has_permissions', DB::table('model_has_permissions'), 'permission_id', $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'channels', Channel::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'video_tasks', VideoTask::withTrashed(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'extra_tasks', ExtraTask::withTrashed(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'ideas', Idea::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'generated_videos', GeneratedVideo::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'report_histories', ReportHistory::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'day_observations', DayObservation::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'vacations', Vacation::query(), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'work_sessions', WorkSession::query(), $firstDataKey);
            $this->writeTable($handle, 'time_offs', TimeOff::query(), $firstDataKey);
        } else {
            $orgRoleIds = Role::where('organization_id', $orgId)->pluck('id');
            $allRoleIds = $orgRoleIds->merge(Role::whereNull('organization_id')->pluck('id'));

            $org = Organization::find($orgId);
            $firstDataKey = $this->rawProp($handle, 'organization', $org ? $org->toJson() : 'null', $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'users', User::where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'roles', Role::whereIn('id', $allRoleIds), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'permissions', Permission::query(), $firstDataKey);
            $firstDataKey = $this->writePivot($handle, 'role_has_permissions', DB::table('role_has_permissions')->whereIn('role_id', $allRoleIds), 'role_id', $firstDataKey);
            $firstDataKey = $this->writePivot($handle, 'model_has_roles', DB::table('model_has_roles')->whereIn('role_id', $orgRoleIds)->orWhereNull('role_id'), 'role_id', $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'channels', Channel::where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'video_tasks', VideoTask::withTrashed()->where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'extra_tasks', ExtraTask::withTrashed()->where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'ideas', Idea::where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'generated_videos', GeneratedVideo::where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'report_histories', ReportHistory::where('organization_id', $orgId), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'vacations', Vacation::whereHas('user', fn ($q) => $q->where('organization_id', $orgId)), $firstDataKey);
            $firstDataKey = $this->writeTable($handle, 'work_sessions', WorkSession::whereHas('videoTask', fn ($q) => $q->where('organization_id', $orgId)), $firstDataKey);
            $this->writeTable($handle, 'time_offs', TimeOff::whereHas('user', fn ($q) => $q->where('organization_id', $orgId)), $firstDataKey);
        }

        fwrite($handle, '}');
        fwrite($handle, '}');
        fclose($handle);

        return $path;
    }

    private function writeTable($handle, string $key, $query, bool $firstKey = true, string $orderBy = 'id'): bool
    {
        if (! $firstKey) {
            fwrite($handle, ',');
        }
        fwrite($handle, '"'.$key.'":[');
        $first = true;
        $query->orderBy($orderBy)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$first) {
            foreach ($rows as $row) {
                if (! $first) {
                    fwrite($handle, ',');
                }
                fwrite($handle, $row->toJson());
                $first = false;
            }
        });
        fwrite($handle, ']');

        return false;
    }

    private function writePivot($handle, string $key, $query, string $orderBy, bool $firstKey = true): bool
    {
        if (! $firstKey) {
            fwrite($handle, ',');
        }
        fwrite($handle, '"'.$key.'":[');
        $first = true;
        $query->orderBy($orderBy)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$first) {
            foreach ($rows as $row) {
                if (! $first) {
                    fwrite($handle, ',');
                }
                fwrite($handle, json_encode((array) $row));
                $first = false;
            }
        });
        fwrite($handle, ']');

        return false;
    }

    private function jsonProp($handle, string $key, mixed $value, bool $leadingComma = true): void
    {
        if ($leadingComma) {
            fwrite($handle, ',');
        }
        fwrite($handle, '"'.$key.'":'.json_encode($value));
    }

    private function rawProp($handle, string $key, string $json, bool $firstKey = true): bool
    {
        if (! $firstKey) {
            fwrite($handle, ',');
        }
        fwrite($handle, '"'.$key.'":'.$json);

        return false;
    }

    private function filterColumns(string $table, array $row, ?int $overrideOrgId = null): array
    {
        $allowed = self::ALLOWED_COLUMNS[$table] ?? null;
        $filtered = $allowed ? Arr::only($row, $allowed) : $row;

        if ($overrideOrgId !== null && isset($filtered['organization_id'])) {
            $filtered['organization_id'] = $overrideOrgId;
        }

        return $filtered;
    }

    public function import(array $data, ?int $userOrgId = null, bool $isSuperAdmin = false): array
    {
        $logs = [];

        DB::transaction(function () use ($data, $userOrgId, $isSuperAdmin, &$logs) {
            $scope = $data['scope'] ?? self::SCOPE_ALL;
            $backupOrgId = $data['organization_id'] ?? null;

            if (! $isSuperAdmin) {
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
                    if (! isset($data[$key])) {
                        continue;
                    }
                    foreach ($data[$key] as $row) {
                        $filtered = $this->filterColumns($key, $row);
                        $modelClass::updateOrCreate(['id' => $row['id']], $filtered);
                    }
                    $logs[] = count($data[$key])." registros en {$key}";
                }

                $pivotTables = ['role_has_permissions', 'model_has_roles', 'model_has_permissions'];
                foreach ($pivotTables as $table) {
                    if (! isset($data[$table])) {
                        continue;
                    }
                    foreach ($data[$table] as $row) {
                        DB::table($table)->updateOrInsert((array) $row);
                    }
                    $logs[] = count($data[$table])." registros en {$table}";
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
                    'work_sessions' => WorkSession::class,
                    'time_offs' => TimeOff::class,
                ];

                foreach ($scopedTables as $key => $modelClass) {
                    if (! isset($data[$key])) {
                        continue;
                    }
                    foreach ($data[$key] as $row) {
                        $exceptColumns = ['created_at', 'updated_at', 'deleted_at'];
                        if ($key === 'users') {
                            $exceptColumns[] = 'password';
                        }
                        $restore = Arr::except($row, $exceptColumns);
                        $restore = $this->filterColumns($key, $restore);
                        $modelClass::withTrashed()->updateOrCreate(['id' => $row['id']], $restore);
                    }
                    $logs[] = count($data[$key])." registros en {$key}";
                }
            } else {
                if (isset($data['organization'])) {
                    $orgData = Arr::except($data['organization'], ['created_at', 'updated_at']);
                    $orgData = $this->filterColumns('organizations', $orgData, $userOrgId);
                    Organization::updateOrCreate(['id' => $data['organization']['id']], $orgData);
                    $logs[] = '1 registro en organization';
                }

                // RBAC tables ONLY imported by Super Admin
                if ($isSuperAdmin) {
                    if (isset($data['roles'])) {
                        foreach ($data['roles'] as $row) {
                            $filtered = $this->filterColumns('roles', $row);
                            Role::updateOrCreate(['id' => $row['id']], $filtered);
                        }
                        $logs[] = count($data['roles']).' registros en roles';
                    }

                    if (isset($data['permissions'])) {
                        foreach ($data['permissions'] as $row) {
                            $filtered = $this->filterColumns('permissions', $row);
                            Permission::updateOrCreate(['id' => $row['id']], $filtered);
                        }
                        $logs[] = count($data['permissions']).' registros en permissions';
                    }

                    $orgPivots = ['role_has_permissions', 'model_has_roles'];
                    foreach ($orgPivots as $table) {
                        if (! isset($data[$table])) {
                            continue;
                        }
                        foreach ($data[$table] as $row) {
                            DB::table($table)->updateOrInsert((array) $row);
                        }
                        $logs[] = count($data[$table])." registros en {$table}";
                    }
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
                    'work_sessions' => WorkSession::class,
                    'time_offs' => TimeOff::class,
                ];

                foreach ($scopedTables as $key => $modelClass) {
                    if (! isset($data[$key])) {
                        continue;
                    }
                    foreach ($data[$key] as $row) {
                        $exceptColumns = ['created_at', 'updated_at', 'deleted_at'];
                        if ($key === 'users') {
                            $exceptColumns[] = 'password';
                        }
                        $restore = Arr::except($row, $exceptColumns);
                        $restore = $this->filterColumns($key, $restore, $userOrgId);
                        $modelClass::withTrashed()->updateOrCreate(['id' => $row['id']], $restore);
                    }
                    $logs[] = count($data[$key])." registros en {$key}";
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
                'work_sessions' => WorkSession::count(),
                'time_offs' => TimeOff::count(),
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
            'vacations' => Vacation::whereHas('user', fn ($q) => $q->where('organization_id', $orgId))->count(),
            'work_sessions' => WorkSession::whereHas('videoTask', fn ($q) => $q->where('organization_id', $orgId))->count(),
            'time_offs' => TimeOff::whereHas('user', fn ($q) => $q->where('organization_id', $orgId))->count(),
        ];
    }

    const SCHEDULE_FILE = 'settings'.DIRECTORY_SEPARATOR.'backup_schedule.json';

    public function getSchedule(): array
    {
        $path = storage_path('app/'.self::SCHEDULE_FILE);
        if (! file_exists($path)) {
            return ['time' => '03:00', 'day' => 'sunday'];
        }

        return json_decode(file_get_contents($path), true);
    }

    public function setSchedule(string $time, string $day): void
    {
        $path = storage_path('app/'.self::SCHEDULE_FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = tempnam($dir, 'backup_sched_');
        file_put_contents($tmp, json_encode(['time' => $time, 'day' => $day, 'updated_at' => now()->toIso8601String()], JSON_PRETTY_PRINT));
        rename($tmp, $path);
    }

    public function getScheduleCron(): string
    {
        $s = $this->getSchedule();
        [$h, $m] = explode(':', $s['time']);

        $dayMap = [
            'monday' => '1', 'tuesday' => '2', 'wednesday' => '3',
            'thursday' => '4', 'friday' => '5', 'saturday' => '6', 'sunday' => '0',
        ];

        return $m.' '.$h.' * * '.($dayMap[$s['day']] ?? '0');
    }
}
