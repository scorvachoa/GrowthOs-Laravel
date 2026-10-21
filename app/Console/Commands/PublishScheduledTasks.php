<?php

namespace App\Console\Commands;

use App\Services\YouTube\AutoPublishService;
use Illuminate\Console\Command;

class PublishScheduledTasks extends Command
{
    protected $signature = 'tasks:publish-scheduled';

    protected $description = 'Marca como publicadas las tareas Programadas cuyo video ya está disponible en YouTube';

    public function handle(AutoPublishService $publisher): int
    {
        $published = $publisher->publishLiveScheduled();

        $this->info("Tareas publicadas automáticamente: {$published}");

        return Command::SUCCESS;
    }
}
