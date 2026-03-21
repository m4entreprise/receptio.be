<?php

use App\Http\Controllers\AgentSettingsController;
use App\Http\Controllers\BackofficeController;
use App\Http\Controllers\TwilioVoiceWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/', [BackofficeController::class, 'overview'])->name('dashboard');
    Route::get('calls', [BackofficeController::class, 'calls'])->name('dashboard.calls');
    Route::get('messages', [BackofficeController::class, 'messages'])->name('dashboard.messages');
    Route::get('agent', [BackofficeController::class, 'agent'])->name('dashboard.agent');
    Route::get('numbers', [BackofficeController::class, 'numbers'])->name('dashboard.numbers');
    Route::get('integrations', [BackofficeController::class, 'integrations'])->name('dashboard.integrations');
    Route::get('workspace', [BackofficeController::class, 'workspace'])->name('dashboard.workspace');
    Route::put('settings', [AgentSettingsController::class, 'update'])->name('dashboard.settings.update');
});

Route::prefix('webhooks/twilio/voice')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->group(function () {
        Route::post('incoming', [TwilioVoiceWebhookController::class, 'incoming'])->name('webhooks.twilio.voice.incoming');
        Route::post('menu', [TwilioVoiceWebhookController::class, 'menu'])->name('webhooks.twilio.voice.menu');
        Route::post('recording', [TwilioVoiceWebhookController::class, 'recording'])->name('webhooks.twilio.voice.recording');
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
