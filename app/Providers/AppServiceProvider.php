<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\PortableFileSystemTemplateFinder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Pollora\Filesystem\Filesystem;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replace the framework's template finder with one that handles the
        // mixed path separators WordPress reports on Windows. Registered here
        // (after PolloraServiceProvider) so the override wins, and before any
        // provider boots so every consumer receives it.
        $this->app->bind(TemplateFinderInterface::class, fn (Application $app): PortableFileSystemTemplateFinder => new PortableFileSystemTemplateFinder(
            $app->get('view')->getFinder(),
            $app->make(Filesystem::class)
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //

        $this->bootRoute();
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
