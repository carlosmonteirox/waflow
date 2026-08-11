<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FlowController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OpenWaEventController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/openapi.yaml', ApiDocumentationController::class)->name('openapi');

Route::prefix('v1')->as('api.')->group(function (): void {
    Route::get('/health', SystemHealthController::class)->name('health');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:waflow-registration')->name('auth.register');
    Route::post('/internal/openwa/events', OpenWaEventController::class)->middleware('throttle:600,1')->name('internal.openwa.events');

    Route::middleware(['waflow.api', 'throttle:waflow-api'])->group(function (): void {
        Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1')->name('auth.verification.resend');

        Route::middleware(['waflow.subscription', 'waflow.audit'])->group(function (): void {
            Route::get('/workspace', [WorkspaceController::class, 'show'])->middleware('waflow.ability:workspace:read')->name('workspace.show');
            Route::patch('/workspace', [WorkspaceController::class, 'update'])->middleware('waflow.ability:workspace:write')->name('workspace.update');
            Route::get('/billing/usage', [BillingController::class, 'usage'])->middleware('waflow.ability:billing:read')->name('billing.usage');
            Route::get('/billing/summary', [BillingController::class, 'summary'])->middleware('waflow.ability:billing:read')->name('billing.summary');
            Route::get('/billing/checkouts', [BillingController::class, 'checkouts'])->middleware('waflow.ability:billing:read')->name('billing.checkouts.index');
            Route::post('/billing/checkouts', [BillingController::class, 'createCheckout'])->middleware('waflow.ability:billing:write')->name('billing.checkouts.store');
            Route::get('/billing/checkouts/{checkout}', [BillingController::class, 'showCheckout'])->middleware('waflow.ability:billing:read')->name('billing.checkouts.show');
            Route::post('/billing/checkouts/{checkout}/cancel', [BillingController::class, 'cancelCheckout'])->middleware('waflow.ability:billing:write')->name('billing.checkouts.cancel');
            Route::get('/billing/invoices', [BillingController::class, 'invoices'])->middleware('waflow.ability:billing:read')->name('billing.invoices.index');
            Route::get('/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])->middleware('waflow.ability:billing:read')->name('billing.invoices.show');
            Route::get('/billing/payments', [BillingController::class, 'payments'])->middleware('waflow.ability:billing:read')->name('billing.payments.index');
            Route::post('/billing/subscription/cancel', [BillingController::class, 'cancelSubscription'])->middleware('waflow.ability:billing:write')->name('billing.subscription.cancel');
            Route::post('/billing/subscription/resume', [BillingController::class, 'resumeSubscription'])->middleware('waflow.ability:billing:write')->name('billing.subscription.resume');

            Route::apiResource('instances', InstanceController::class)
                ->middlewareFor(['index', 'show'], 'waflow.ability:instances:read')
                ->middlewareFor(['store', 'update', 'destroy'], 'waflow.ability:instances:write');
            Route::post('/instances/{instance}/connect', [InstanceController::class, 'connect'])->middleware('waflow.ability:instances:write')->name('instances.connect');
            Route::post('/instances/{instance}/disconnect', [InstanceController::class, 'disconnect'])->middleware('waflow.ability:instances:write')->name('instances.disconnect');
            Route::get('/instances/{instance}/status', [InstanceController::class, 'status'])->middleware('waflow.ability:instances:read')->name('instances.status');
            Route::get('/instances/{instance}/qr', [InstanceController::class, 'qr'])->middleware('waflow.ability:instances:read')->name('instances.qr');
            Route::get('/instances/{instance}/groups', [InstanceController::class, 'groups'])->middleware('waflow.ability:groups:read')->name('instances.groups');
            Route::get('/instances/{instance}/channels', [InstanceController::class, 'channels'])->middleware('waflow.ability:channels:read')->name('instances.channels');
            Route::get('/instances/{instance}/remote-contacts', [InstanceController::class, 'contacts'])->middleware('waflow.ability:contacts:read')->name('instances.remote-contacts');

            Route::apiResource('messages', MessageController::class)->only(['index', 'store', 'show'])
                ->middlewareFor(['index', 'show'], 'waflow.ability:messages:read')
                ->middlewareFor('store', 'waflow.ability:messages:write');
            Route::post('/messages/bulk', [MessageController::class, 'bulk'])->middleware('waflow.ability:messages:write')->name('messages.bulk');
            Route::apiResource('portals', PortalController::class)
                ->middlewareFor(['index', 'show'], 'waflow.ability:portals:read')
                ->middlewareFor(['store', 'update', 'destroy'], 'waflow.ability:portals:write');
            Route::post('/portals/{portal}/verify', [PortalController::class, 'verify'])->middleware('waflow.ability:portals:write')->name('portals.verify');

            Route::apiResource('flows', FlowController::class)
                ->middlewareFor(['index', 'show'], 'waflow.ability:flows:read')
                ->middlewareFor(['store', 'update', 'destroy'], 'waflow.ability:flows:write');
            Route::get('/flows/{flow}/executions', [FlowController::class, 'executions'])->middleware('waflow.ability:flows:read')->name('flows.executions');
            Route::post('/flows/{flow}/test', [FlowController::class, 'test'])->middleware('waflow.ability:flows:write')->name('flows.test');
            Route::apiResource('contacts', ContactController::class)
                ->middlewareFor(['index', 'show'], 'waflow.ability:contacts:read')
                ->middlewareFor(['store', 'update', 'destroy'], 'waflow.ability:contacts:write');
            Route::apiResource('webhooks', WebhookController::class)
                ->middlewareFor(['index', 'show'], 'waflow.ability:webhooks:read')
                ->middlewareFor(['store', 'update', 'destroy'], 'waflow.ability:webhooks:write');
            Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])->middleware('waflow.ability:webhooks:write')->name('webhooks.test');

            Route::get('/api-keys', [ApiKeyController::class, 'index'])->middleware('waflow.ability:api-keys:read')->name('api-keys.index');
            Route::post('/api-keys', [ApiKeyController::class, 'store'])->middleware('waflow.ability:api-keys:write')->name('api-keys.store');
            Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->whereNumber('apiKey')->middleware('waflow.ability:api-keys:write')->name('api-keys.destroy');

            Route::get('/team/members', [TeamController::class, 'members'])->middleware('waflow.ability:team:read')->name('team.members.index');
            Route::patch('/team/members/{member}', [TeamController::class, 'updateMember'])->middleware('waflow.ability:team:write')->name('team.members.update');
            Route::delete('/team/members/{member}', [TeamController::class, 'removeMember'])->middleware('waflow.ability:team:write')->name('team.members.destroy');
            Route::get('/team/invitations', [TeamController::class, 'invitations'])->middleware('waflow.ability:team:read')->name('team.invitations.index');
            Route::post('/team/invitations', [TeamController::class, 'invite'])->middleware('waflow.ability:team:write')->name('team.invitations.store');
            Route::post('/team/invitations/{invitation}/resend', [TeamController::class, 'resend'])->middleware('waflow.ability:team:write')->name('team.invitations.resend');
            Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revoke'])->middleware('waflow.ability:team:write')->name('team.invitations.destroy');
        });
    });
});
