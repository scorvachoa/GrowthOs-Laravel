<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ExtraTask;
use App\Models\Idea;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Console\Command;
use PDO;

class ImportPythonData extends Command
{
    protected $signature = 'import:python-data {sqlite=E:\Python\Git\GrowthOS\database\tasks.db}';

    protected $description = 'Import tasks, extra tasks, channels and ideas from Python GrowthOS SQLite database';

    public function handle(): int
    {
        $path = $this->argument('sqlite');

        if (!file_exists($path)) {
            $this->error("SQLite database not found: {$path}");
            return Command::FAILURE;
        }

        $db = new PDO("sqlite:{$path}");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $defaultUserId = User::query()->where('email', 'admin@growthos.com')->value('id') ?? User::query()->first()?->id;
        if (!$defaultUserId) {
            $this->error('No users found in database. Run db:seed first.');
            return Command::FAILURE;
        }

        $this->importChannels($db);
        $this->importVideoTasks($db, $defaultUserId);
        $this->importExtraTasks($db, $defaultUserId);
        $this->importIdeas($db);

        return Command::SUCCESS;
    }

    protected function importChannels(PDO $db): void
    {
        $this->info('Importing channels...');

        $rows = $db->query('SELECT id, name, color, youtube_channel_id, channel_url, created_at FROM channels ORDER BY id');

        $count = 0;
        foreach ($rows as $row) {
            $exists = Channel::query()->where('youtube_channel_id', $row['youtube_channel_id'])->exists();
            if ($exists) {
                $this->warn("  Channel '{$row['name']}' already exists, skipping");
                continue;
            }

            Channel::query()->create([
                'name' => $row['name'],
                'color' => $row['color'] ?: '#4f46e5',
                'youtube_channel_id' => $row['youtube_channel_id'] ?: null,
                'channel_url' => $row['channel_url'] ?: null,
            ]);
            $count++;
        }

        $this->info("  Imported {$count} channels");
    }

    protected function importVideoTasks(PDO $db, int $userId): void
    {
        $this->info('Importing video tasks...');

        $statusMap = [
            'pendiente' => 'pending',
            'completado' => 'published',
        ];

        $channelMap = [];
        $rows = $db->query('SELECT * FROM tasks ORDER BY id');
        $count = 0;

        foreach ($rows as $row) {
            $exists = VideoTask::query()
                ->where('task_date', $row['fecha'])
                ->where('time_range', $row['bloque'])
                ->exists();

            if ($exists) {
                $this->warn("  Task on {$row['fecha']} {$row['bloque']} already exists, skipping");
                continue;
            }

            $status = $statusMap[$row['status']] ?? 'pending';
            $channelId = null;

            if ($row['channel_id']) {
                if (!isset($channelMap[$row['channel_id']])) {
                    $ch = $db->query("SELECT id, name FROM channels WHERE id = {$row['channel_id']}")->fetch(PDO::FETCH_ASSOC);
                    if ($ch) {
                        $laravelChannel = Channel::query()->where('name', $ch['name'])->first();
                        $channelMap[$row['channel_id']] = $laravelChannel?->id;
                    } else {
                        $channelMap[$row['channel_id']] = null;
                    }
                }
                $channelId = $channelMap[$row['channel_id']];
            }

            VideoTask::query()->create([
                'task_date' => $row['fecha'],
                'time_range' => $row['bloque'],
                'title' => $row['idea_video'],
                'script' => $row['guion_video'] ?: null,
                'copy' => $row['copy_video'] ?: null,
                'key_phrases' => null,
                'youtube_url' => $row['youtube_url'] ?: null,
                'channel_id' => $channelId,
                'status' => $status,
                'created_by' => $userId,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
            $count++;
        }

        $this->info("  Imported {$count} video tasks");
    }

    protected function importExtraTasks(PDO $db, int $userId): void
    {
        $this->info('Importing extra tasks...');

        $statusMap = [
            'pendiente' => 'pending',
            'completado' => 'completado',
        ];

        $rows = $db->query('SELECT * FROM extra_tasks ORDER BY id');
        $count = 0;

        foreach ($rows as $row) {
            $exists = ExtraTask::query()
                ->where('task_date', $row['fecha'])
                ->where('time_range', $row['hora'])
                ->where('title', $row['titulo'])
                ->exists();

            if ($exists) {
                $this->warn("  Extra task '{$row['titulo']}' on {$row['fecha']} already exists, skipping");
                continue;
            }

            $status = $statusMap[$row['status']] ?? 'pending';

            ExtraTask::query()->create([
                'task_date' => $row['fecha'],
                'time_range' => $row['hora'],
                'title' => $row['titulo'],
                'status' => $status,
                'location' => $row['location'] ?: 'oficina',
                'created_by' => $userId,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
            $count++;
        }

        $this->info("  Imported {$count} extra tasks");
    }

    protected function importIdeas(PDO $db): void
    {
        $this->info('Importing ideas...');

        $channelMap = [];
        $rows = $db->query('SELECT * FROM ideas ORDER BY id');
        $count = 0;

        foreach ($rows as $row) {
            if (!isset($channelMap[$row['channel_id']])) {
                $ch = $db->query("SELECT id, name FROM channels WHERE id = {$row['channel_id']}")->fetch(PDO::FETCH_ASSOC);
                if ($ch) {
                    $laravelChannel = \App\Models\Channel::query()->where('name', $ch['name'])->first();
                    $channelMap[$row['channel_id']] = $laravelChannel?->id;
                } else {
                    $channelMap[$row['channel_id']] = null;
                }
            }

            $channelId = $channelMap[$row['channel_id']];
            if (!$channelId) {
                $this->warn("  Idea #{$row['id']} has no matching channel, skipping");
                continue;
            }

            $exists = Idea::query()
                ->where('channel_id', $channelId)
                ->where('content', $row['content'])
                ->exists();

            if ($exists) {
                $this->warn("  Idea '{$row['content']}' already exists, skipping");
                continue;
            }

            Idea::query()->create([
                'channel_id' => $channelId,
                'content' => $row['content'],
                'is_used' => (bool) $row['is_used'],
                'tags' => $row['tags'] ?? '',
                'priority' => (int) ($row['priority'] ?? 0),
                'category' => $row['category'] ?? '',
                'created_at' => $row['created_at'] ?: now(),
                'updated_at' => $row['created_at'] ?: now(),
            ]);
            $count++;
        }

        $this->info("  Imported {$count} ideas");
    }
}
