<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CheckEmailController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Panel\PanelController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    Route::get('/two-factor', TwoFactorPromptController::class)->name('two-factor.challenge');
});

Route::get('/check-email', CheckEmailController::class)->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('verified')->group(function (): void {
        Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
        Route::get('/instances', [PanelController::class, 'instances'])->name('panel.instances');
        Route::post('/instances', [PanelController::class, 'storeInstance'])->name('panel.instances.store');
        Route::post('/instances/{instance}/connect', [PanelController::class, 'connectInstance'])->name('panel.instances.connect');
        Route::post('/instances/{instance}/disconnect', [PanelController::class, 'disconnectInstance'])->name('panel.instances.disconnect');

        Route::get('/flows', [PanelController::class, 'flows'])->name('panel.flows');
        Route::post('/flows', [PanelController::class, 'storeFlow'])->name('panel.flows.store');

        Route::get('/messages', [PanelController::class, 'messages'])->name('panel.messages');

        Route::get('/contacts', [PanelController::class, 'contacts'])->name('panel.contacts');
        Route::post('/contacts', [PanelController::class, 'storeContact'])->name('panel.contacts.store');

        Route::get('/developers', [PanelController::class, 'developers'])->name('panel.developers');

        Route::get('/webhooks', [PanelController::class, 'webhooks'])->name('panel.webhooks');
        Route::post('/webhooks', [PanelController::class, 'storeWebhook'])->name('panel.webhooks.store');
        Route::delete('/webhooks/{webhook}', [PanelController::class, 'destroyWebhook'])->name('panel.webhooks.destroy');

        Route::get('/api-keys', [PanelController::class, 'apiKeys'])->name('panel.api-keys');
        Route::post('/api-keys', [PanelController::class, 'storeApiKey'])->name('panel.api-keys.store');
        Route::delete('/api-keys/{apiKey}', [PanelController::class, 'destroyApiKey'])->whereNumber('apiKey')->name('panel.api-keys.destroy');

        Route::get('/team', [PanelController::class, 'team'])->name('panel.team');
        Route::post('/team', [PanelController::class, 'storeTeamMember'])->name('panel.team.store');

        Route::get('/billing', [PanelController::class, 'billing'])->name('panel.billing');
        Route::get('/settings', [PanelController::class, 'settings'])->name('panel.settings');
        Route::patch('/settings', [PanelController::class, 'updateSettings'])->name('panel.settings.update');
    });
});
