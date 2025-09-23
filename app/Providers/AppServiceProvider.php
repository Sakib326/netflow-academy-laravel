<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\ExamResponse;
use App\Observers\ExamResponseObserver;

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
        Order::observe(OrderObserver::class);

        ExamResponse::observe(ExamResponseObserver::class);

    }
}
