<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('vnd', fn (string $expression) => "<?php echo e(\\App\\Support\\Format::vnd((int) ({$expression}))); ?>");
        Blade::directive('kg', fn (string $expression) => "<?php echo e(\\App\\Support\\Format::kg({$expression})); ?>");
    }
}
