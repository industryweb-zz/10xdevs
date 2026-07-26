<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\AnthropicFlashcardGenerator;
use App\Services\FlashcardGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: config('services.anthropic.key'),
        ));

        $this->app->bind(FlashcardGenerator::class, AnthropicFlashcardGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
