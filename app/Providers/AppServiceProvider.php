<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Interfaces\PagePermissionRepositoryInterface;
use App\Domain\Interfaces\UserRepositoryInterface;
use App\Infrastructure\Repositories\PagePermissionRepository;
use App\Infrastructure\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PagePermissionRepositoryInterface::class, PagePermissionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
