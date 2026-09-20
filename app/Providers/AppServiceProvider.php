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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Sop::class, SopPolicy::class);

        Event::listen(Failed::class, LogFailedLoginAttempt::class);
    }
}
