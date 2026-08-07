<?php
namespace App\Providers;
use App\Models\CalendarImport;
use App\Models\Evidence;
use App\Models\ScheduledLoad;
use App\Policies\CalendarImportPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\ScheduledLoadPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        if (env('FLY_APP_NAME') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::policy(CalendarImport::class,CalendarImportPolicy::class);
        Gate::policy(Evidence::class,EvidencePolicy::class);
        Gate::policy(ScheduledLoad::class,ScheduledLoadPolicy::class);
        Gate::define('operations.view', fn (\App\Models\User $user) => $user->hasPermission('operations.view'));
        Gate::define('operations.manage', fn (\App\Models\User $user) => $user->hasPermission('operations.manage'));
        Gate::define('incidents.manage', fn (\App\Models\User $user) => $user->hasPermission('incidents.manage'));
        Gate::define('backups.view', fn (\App\Models\User $user) => $user->hasPermission('backups.view'));
    }
}