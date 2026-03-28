<?php

use App\Http\Controllers\AgentSettingsController;
use App\Http\Controllers\BackofficeController;
use App\Http\Controllers\BackofficeMessageController;
use App\Http\Controllers\BackofficeRecordingController;
use App\Http\Controllers\Internal\RealtimeCallController;
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
    Route::get('calls/{call}', [BackofficeController::class, 'showCall'])->name('dashboard.calls.show');
    Route::get('messages', [BackofficeController::class, 'messages'])->name('dashboard.messages');
    Route::get('messages/{message}/recording', [BackofficeRecordingController::class, 'show'])->name('dashboard.messages.recording');
    Route::patch('messages/{message}', [BackofficeMessageController::class, 'update'])->name('dashboard.messages.update');
    Route::get('agent', [BackofficeController::class, 'agent'])->name('dashboard.agent');
    Route::get('numbers', [BackofficeController::class, 'numbers'])->name('dashboard.numbers');
    Route::get('integrations', [BackofficeController::class, 'integrations'])->name('dashboard.integrations');
    Route::get('workspace', [BackofficeController::class, 'workspace'])->name('dashboard.workspace');
    Route::put('settings', [AgentSettingsController::class, 'update'])->name('dashboard.settings.update');
});

Route::prefix('webhooks/twilio/voice')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware(['twilio.signature'])
    ->group(function () {
        Route::post('incoming', [TwilioVoiceWebhookController::class, 'incoming'])->name('webhooks.twilio.voice.incoming');
        Route::post('menu', [TwilioVoiceWebhookController::class, 'menu'])->name('webhooks.twilio.voice.menu');
        Route::post('status', [TwilioVoiceWebhookController::class, 'status'])->name('webhooks.twilio.voice.status');
        Route::post('recording', [TwilioVoiceWebhookController::class, 'recording'])->name('webhooks.twilio.voice.recording');
        Route::post('ping', [TwilioVoiceWebhookController::class, 'ping'])->name('webhooks.twilio.voice.ping');
    });

Route::prefix('internal/realtime')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware(['realtime.internal'])
    ->group(function () {
        Route::get('calls/{callSid}/bootstrap', [RealtimeCallController::class, 'bootstrap'])->name('internal.realtime.calls.bootstrap');
        Route::post('calls/{callSid}/turns', [RealtimeCallController::class, 'storeTurn'])->name('internal.realtime.calls.turns.store');
        Route::post('calls/{callSid}/resolution', [RealtimeCallController::class, 'storeResolution'])->name('internal.realtime.calls.resolution.store');
        Route::post('calls/{callSid}/transfer', [RealtimeCallController::class, 'storeTransfer'])->name('internal.realtime.calls.transfer.store');
        Route::post('calls/{callSid}/fallback', [RealtimeCallController::class, 'storeFallback'])->name('internal.realtime.calls.fallback.store');
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
