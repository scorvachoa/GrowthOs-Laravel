<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Idea;
use App\Services\AI\AIContentService;
use Illuminate\Console\Command;

class GenerateIdeas extends Command
{
    protected $signature = 'ideas:generate {--count=5 : Ideas a generar por canal} {--max-pending=30 : Salta canales que ya tengan este número de ideas pendientes}';

    protected $description = 'Genera ideas de videos con IA para todos los canales (cron semanal)';

    public function handle(AIContentService $ai): int
    {
        $count = max(1, min(20, (int) $this->option('count')));
        $maxPending = max(0, (int) $this->option('max-pending'));

        $channels = Channel::query()->orderBy('id')->get();
        $generated = 0;

        foreach ($channels as $channel) {
            $existing = Idea::query()
                ->where('channel_id', $channel->id)
                ->where('is_used', false)
                ->limit(60)
                ->pluck('content')
                ->all();

            if (count($existing) >= $maxPending) {
                $this->line("Saltando canal #{$channel->id} ({$channel->name}): ".count($existing).' ideas pendientes.');

                continue;
            }

            try {
                $ideas = $ai->generateIdeas($channel->name, $existing, $count);
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Error generando ideas para canal #{$channel->id} ({$channel->name}): {$e->getMessage()}");

                continue;
            }

            if ($ideas === []) {
                $this->line("Canal #{$channel->id} ({$channel->name}): la IA no devolvió ideas nuevas.");

                continue;
            }

            $now = now();
            Idea::insert(array_map(fn ($content) => [
                'channel_id' => $channel->id,
                'content' => $content,
                'organization_id' => $channel->organization_id,
                'created_by' => null,
                'is_used' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ideas));

            $generated += count($ideas);
            $this->info("Canal #{$channel->id} ({$channel->name}): ".count($ideas).' ideas generadas.');
        }

        $this->info("Total: {$generated} ideas generadas.");

        return Command::SUCCESS;
    }
}
