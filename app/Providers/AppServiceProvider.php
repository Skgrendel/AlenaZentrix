<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\User;
use App\Observers\CompanyObserver;
use App\Observers\UserObserver;
use App\Services\TenantService;
use App\Support\ApiRateLimiters;
use App\Support\ArchivoUrl;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\ToggleButtons;
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
        User::observe(UserObserver::class);
        ApiRateLimiters::register();
        $this->configureDefaults();
        $this->configureS3TemporaryUrls();
        $this->configureSiNoButtons();
    }

    /**
     * Registra ToggleButtons::siNo(): botones Sí/No con "No" preseleccionado que siempre guardan un booleano.
     * Se necesita porque los formularios llaman a fill() con un arreglo y Filament no aplica default() en ese caso.
     */
    protected function configureSiNoButtons(): void
    {
        ToggleButtons::macro('siNo', function (): ToggleButtons {
            /** @var ToggleButtons $this */
            return $this
                ->boolean('Sí', 'No')
                ->inline()
                ->afterStateHydrated(fn (ToggleButtons $component, mixed $state) => $component->state($state ?? 0))
                ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state);
        });
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
