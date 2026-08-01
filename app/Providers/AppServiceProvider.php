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
        // @money(1234.5) => $1,234.50
        Blade::directive('money', function (string $expression): string {
            return "<?php echo e(config('app.currency_symbol').number_format((float) ({$expression}), 2)); ?>";
        });

        // @qty(1234) => 1,234
        Blade::directive('qty', function (string $expression): string {
            return "<?php echo e(number_format((float) ({$expression}))); ?>";
        });
    }
}
