<?php

namespace App\Providers;

use App\Support\VoicemailInsights\GeneratesVoicemailInsights;
use App\Support\VoicemailInsights\HeuristicVoicemailInsightGenerator;
use App\Support\VoicemailInsights\OpenAiVoicemailInsightGenerator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HeuristicVoicemailInsightGenerator::class);

        $this->app->singleton(GeneratesVoicemailInsights::class, function ($app) {
            $apiKey = config('services.openai.api_key');
            $transcriptionModel = config('services.openai.transcription_model');
            $textModel = config('services.openai.text_model');

            if ($apiKey) {
                return new OpenAiVoicemailInsightGenerator(
                    http: $app->make(HttpFactory::class),
                    heuristic: $app->make(HeuristicVoicemailInsightGenerator::class),
                    apiKey: $apiKey,
                    transcriptionModel: $transcriptionModel,
                    textModel: $textModel,
                );
            }

            return $app->make(HeuristicVoicemailInsightGenerator::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
