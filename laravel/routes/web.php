<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CheckEmailController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Partner\PartnerController;
use App\Http\Controllers\Platform\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:waflow-registration')->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:waflow-password-email')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:waflow-password-reset')->name('password.store');
    Route::get('/two-factor', [TwoFactorPromptController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorPromptController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.store');
    Route::post('/two-factor/resend', [TwoFactorPromptController::class, 'resend'])->middleware('throttle:2,1')->name('two-factor.resend');
});

Route::get('/check-email', CheckEmailController::class)->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/invitations/{token}', InvitationAcceptanceController::class)->name('invitations.accept');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('verified')->group(function (): void {
        Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
        Route::post('/workspaces/{workspace}/select', [PanelController::class, 'switchWorkspace'])->name('panel.workspaces.select');
        Route::get('/instances', [PanelController::class, 'instances'])->name('panel.instances');
        Route::post('/instances', [PanelController::class, 'storeInstance'])->name('panel.instances.store');
        Route::post('/instances/{instance}/connect', [PanelController::class, 'connectInstance'])->name('panel.instances.connect');
        Route::post('/instances/{instance}/disconnect', [PanelController::class, 'disconnectInstance'])->name('panel.instances.disconnect');

        Route::get('/flows', [PanelController::class, 'flows'])->name('panel.flows');
        Route::post('/flows', [PanelController::class, 'storeFlow'])->name('panel.flows.store');
        Route::post('/flows/{flow}/toggle', [PanelController::class, 'toggleFlow'])->name('panel.flows.toggle');
        Route::post('/flows/{flow}/test', [PanelController::class, 'testFlow'])->name('panel.flows.test');

        Route::get('/messages', [PanelController::class, 'messages'])->name('panel.messages');
        Route::post('/messages', [PanelController::class, 'sendMessage'])->middleware('throttle:30,1')->name('panel.messages.store');

        Route::get('/contacts', [PanelController::class, 'contacts'])->name('panel.contacts');
        Route::post('/contacts', [PanelController::class, 'storeContact'])->name('panel.contacts.store');

        Route::get('/portals', [PanelController::class, 'portals'])->name('panel.portals');
        Route::post('/portals', [PanelController::class, 'storePortal'])->name('panel.portals.store');
        Route::post('/portals/{portal}/verify', [PanelController::class, 'verifyPortal'])->name('panel.portals.verify');
        Route::delete('/portals/{portal}', [PanelController::class, 'destroyPortal'])->name('panel.portals.destroy');

        Route::get('/developers', [PanelController::class, 'developers'])->name('panel.developers');

        Route::get('/webhooks', [PanelController::class, 'webhooks'])->name('panel.webhooks');
        Route::post('/webhooks', [PanelController::class, 'storeWebhook'])->middleware('throttle:10,1')->name('panel.webhooks.store');
        Route::delete('/webhooks/{webhook}', [PanelController::class, 'destroyWebhook'])->name('panel.webhooks.destroy');

        Route::get('/api-keys', [PanelController::class, 'apiKeys'])->name('panel.api-keys');
        Route::post('/api-keys', [PanelController::class, 'storeApiKey'])->name('panel.api-keys.store');
        Route::delete('/api-keys/{apiKey}', [PanelController::class, 'destroyApiKey'])->whereNumber('apiKey')->name('panel.api-keys.destroy');

        Route::get('/team', [PanelController::class, 'team'])->name('panel.team');
        Route::post('/team', [PanelController::class, 'storeTeamMember'])->name('panel.team.store');
        Route::delete('/team/invitations/{invitation}', [PanelController::class, 'revokeTeamInvitation'])->name('panel.team.invitations.destroy');

        Route::get('/billing', [PanelController::class, 'billing'])->name('panel.billing');
        Route::post('/billing/checkout', [PanelController::class, 'createCheckout'])->name('panel.billing.checkout');
        Route::post('/billing/subscription/cancel', [PanelController::class, 'cancelSubscription'])->name('panel.billing.cancel');
        Route::post('/billing/subscription/resume', [PanelController::class, 'resumeSubscription'])->name('panel.billing.resume');
        Route::get('/settings', [PanelController::class, 'settings'])->name('panel.settings');
        Route::patch('/settings', [PanelController::class, 'updateSettings'])->name('panel.settings.update');

        Route::prefix('admin')->as('admin.')->middleware('platform.admin')->group(function (): void {
            Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/companies', [AdminController::class, 'companies'])->name('companies');
            Route::post('/companies/{workspace}/status', [AdminController::class, 'updateWorkspaceStatus'])->name('companies.status');
            Route::get('/users', [AdminController::class, 'users'])->name('users');
            Route::post('/users/{user}/admin', [AdminController::class, 'updateUserAdmin'])->name('users.admin');
            Route::get('/partners', [AdminController::class, 'partners'])->name('partners');
            Route::post('/partners', [AdminController::class, 'storePartner'])->name('partners.store');
            Route::post('/partners/{partner}/status', [AdminController::class, 'updatePartnerStatus'])->name('partners.status');
            Route::get('/plans', [AdminController::class, 'plans'])->name('plans');
            Route::post('/plans', [AdminController::class, 'storePlan'])->name('plans.store');
            Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
            Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
            Route::patch('/subscriptions/{subscription}', [AdminController::class, 'updateSubscription'])->name('subscriptions.update');
        });

        Route::prefix('partner')->as('partner.')->middleware('partner.member')->group(function (): void {
            Route::get('/', [PartnerController::class, 'dashboard'])->name('dashboard');
            Route::post('/select/{partner}', [PartnerController::class, 'switchPartner'])->name('select');
            Route::get('/customers', [PartnerController::class, 'customers'])->name('customers');
            Route::get('/finance', [PartnerController::class, 'finance'])->name('finance');
            Route::get('/settings', [PartnerController::class, 'settings'])->name('settings');
            Route::patch('/settings', [PartnerController::class, 'updateSettings'])->name('settings.update');
            Route::post('/members', [PartnerController::class, 'storeMember'])->name('members.store');
            Route::delete('/members/{member}', [PartnerController::class, 'destroyMember'])->name('members.destroy');
        });
    });
});
