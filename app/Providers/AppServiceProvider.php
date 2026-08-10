<?php

namespace App\Providers;

use App\Models\Company;
use App\Observers\CompanyObserver;
use App\Services\TenantService;
use App\Support\ArchivoUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Company::observe(CompanyObserver::class);
        $this->configureDefaults();
        $this->configureS3TemporaryUrls();
    }

    protected function configureS3TemporaryUrls(): void
    {
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTime $expiration, array $options): string => ArchivoUrl::temporal(
                $path,
                now()->diffInMinutes($expiration),
            )
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
