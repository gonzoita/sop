<?php

namespace App\Providers;

use App\Listeners\LogFailedLoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

use App\Models\Sop;
use App\Policies\SopPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->bind(
            \App\Services\OpenRouter\Contracts\AiProvider::class,
            \App\Services\OpenRouter\OpenRouterClient::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Sop::class, SopPolicy::class);

        Event::listen(Failed::class, LogFailedLoginAttempt::class);
        Event::listen(\App\Events\ClientCreated::class, \App\Listeners\ProcessAutomationTriggers::class);
        Event::listen(\App\Events\RunCompleted::class, \App\Listeners\ProcessAutomationTriggers::class);
        Event::listen(\App\Events\RunCompleted::class, function (\App\Events\RunCompleted $event) {
            $run = $event->run;
            if ($run->started_by && ($starter = \App\Models\User::find($run->started_by))) {
                $starter->notify(new \App\Notifications\RunCompletedNotification($run));
            }
        });
        Event::listen(\App\Events\RunStepApproved::class, \App\Listeners\ProcessAutomationTriggers::class);
    }
}
