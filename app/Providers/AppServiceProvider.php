<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\ExtraTask;
use App\Models\GeneratedVideo;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\User;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Observers\NotificationObserver;
use App\Policies\ChannelPolicy;
use App\Policies\ExtraTaskPolicy;
use App\Policies\GeneratedVideoPolicy;
use App\Policies\IdeaPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ReportHistoryPolicy;
use App\Policies\UserPolicy;
use App\Policies\VideoTaskPolicy;
use App\Policies\WorkSessionPolicy;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(VideoTask::class, VideoTaskPolicy::class);
        Gate::policy(ExtraTask::class, ExtraTaskPolicy::class);
        Gate::policy(Idea::class, IdeaPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(GeneratedVideo::class, GeneratedVideoPolicy::class);
        Gate::policy(ReportHistory::class, ReportHistoryPolicy::class);
        Gate::policy(WorkSession::class, WorkSessionPolicy::class);

        DatabaseNotification::observe(NotificationObserver::class);
    }
}
