<?php

namespace App\Providers;

use App\Contracts\Crm\Meta\MetaClient;
use App\Infrastructure\Crm\Meta\GraphMetaAdapter;
use App\Services\Ai\Contracts\EmbeddingClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiEmbeddingClient;
use App\Services\Ai\GeminiKeyPool;
use App\Services\Ai\LocalEmbeddingClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MetaClient::class, GraphMetaAdapter::class);
        $this->registerAi();
    }

    /**
     * Nexus IA: el proveedor de embeddings se elige por configuración
     * (NEXUS_AI_DRIVER) y se resuelve en cada uso, no una sola vez.
     */
    protected function registerAi(): void
    {
        $this->app->bind(GeminiKeyPool::class, static fn (): GeminiKeyPool => GeminiKeyPool::fromConfig());

        $this->app->bind(EmbeddingClient::class, function ($app): EmbeddingClient {
            return config('nexus_ai.driver') === 'local'
                ? new LocalEmbeddingClient((int) config('nexus_ai.gemini.dimensions'))
                : new GeminiEmbeddingClient($app->make(GeminiClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
