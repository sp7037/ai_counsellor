<?php

use App\Http\Controllers\Tenant\KnowledgeDocumentDownloadController;
use App\Http\Controllers\Tenant\KnowledgeImportTemplateController;
use App\Http\Controllers\Tenant\PaymentVerificationController;
use App\Http\Controllers\Webhooks\MessagingWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:'.config('payments.webhook_rate_limit', '120,1'))
    ->name('webhooks.payments');

Route::match(['get', 'post'], '/webhooks/messaging/{provider}', MessagingWebhookController::class)
    ->where('provider', 'meta|fake')
    ->middleware('throttle:'.config('messaging.webhook_rate_limit', '240,1'))
    ->name('webhooks.messaging');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return redirect(app(\App\Services\Auth\PostLoginRedirect::class)->intendedUrl(auth()->user()));
    })->name('dashboard');

    Volt::route('app/select-tenant', 'tenant.select')->name('tenant.select');

    Route::prefix('platform')
        ->middleware('platform.admin')
        ->name('platform.')
        ->group(function () {
            Volt::route('/', 'platform.overview')->name('overview');
            Volt::route('tenants', 'platform.tenants.index')->name('tenants.index');
            Volt::route('tenants/create', 'platform.tenants.create')->name('tenants.create');
            Volt::route('tenants/{tenant}', 'platform.tenants.show')->name('tenants.show');
            Volt::route('tenants/{tenant}/subscription', 'platform.tenants.subscription')->name('tenants.subscription');
            Volt::route('plans', 'platform.plans.index')->name('plans.index');
            Volt::route('plans/{plan}', 'platform.plans.show')->name('plans.show');
            Volt::route('ai-operations', 'platform.ai-operations.index')->name('ai-operations.index');
            Volt::route('usage', 'platform.usage.index')->name('usage.index');
            Volt::route('audit-logs', 'platform.audit-logs.index')->name('audit-logs.index');
            Volt::route('settings', 'platform.settings.index')->name('settings.index');
            Volt::route('failed-runs', 'platform.failed-runs.index')->name('failed-runs.index');
            Volt::route('system-health', 'platform.system-health.index')->name('system-health.index');
            Volt::route('payments', 'platform.payments.index')->name('payments.index');
            Volt::route('payments/{payment}', 'platform.payments.show')->name('payments.show');
            Volt::route('payment-orders', 'platform.payment-orders.index')->name('payment-orders.index');
            Volt::route('integrations', 'platform.integrations.index')->name('integrations.index');
            Volt::route('tenants/{tenant}/payments', 'platform.tenants.payments')->name('tenants.payments');
            Volt::route('plan-change-requests', 'platform.plan-change-requests.index')->name('plan-change-requests.index');
            Volt::route('account-lookup', 'platform.account-lookup')->name('account-lookup');
        });

    Route::prefix('app/{tenant}')
        ->middleware('tenant.resolve')
        ->name('tenant.')
        ->group(function () {
            Volt::route('subscription', 'tenant.subscription')->name('subscription');

            Route::middleware('tenant.billing')->prefix('subscription')->name('subscription.')->group(function () {
                Volt::route('plans', 'tenant.subscription.plans')->name('plans');
                Volt::route('checkout/{plan}', 'tenant.subscription.checkout')->name('checkout');
                Volt::route('payment/{order}/success', 'tenant.subscription.payment-success')->name('payment.success');
                Volt::route('payment/{order}/failed', 'tenant.subscription.payment-failed')->name('payment.failed');
                Volt::route('payment/{payment}/receipt', 'tenant.subscription.receipt')->name('payment.receipt');
                Route::post('payments/verify', PaymentVerificationController::class)->name('payments.verify');
            });

            Route::middleware('tenant.operational')->group(function () {
                Volt::route('dashboard', 'tenant.dashboard')->name('dashboard');
                Volt::route('members', 'tenant.members.index')->name('members.index');
                Volt::route('notes', 'tenant.notes.index')->name('notes.index');
                Volt::route('widget', 'tenant.widget.index')->name('widget.index');
                Volt::route('widget/conversations', 'tenant.widget.conversations')->name('widget.conversations');

                Route::prefix('configuration')->name('configuration.')->group(function () {
                    Volt::route('/', 'tenant.configuration.index')->name('index');
                    Volt::route('branding', 'tenant.configuration.branding')->name('branding');
                    Volt::route('assistant', 'tenant.configuration.assistant')->name('assistant');
                    Volt::route('office-hours', 'tenant.configuration.office-hours')->name('office-hours');
                    Volt::route('services', 'tenant.configuration.services')->name('services');
                    Volt::route('courses', 'tenant.configuration.courses')->name('courses');
                    Volt::route('institutions', 'tenant.configuration.institutions')->name('institutions');
                    Volt::route('locations', 'tenant.configuration.locations')->name('locations');
                });

                Route::prefix('knowledge')->name('knowledge.')->group(function () {
                    Volt::route('/', 'tenant.knowledge.index')->name('index');
                    Volt::route('items', 'tenant.knowledge.items')->name('items');
                    Volt::route('fees', 'tenant.knowledge.fees')->name('fees');
                    Volt::route('eligibility', 'tenant.knowledge.eligibility')->name('eligibility');
                    Volt::route('documents', 'tenant.knowledge.documents')->name('documents');
                    Volt::route('course-institutions', 'tenant.knowledge.course-institutions')->name('course-institutions');
                    Volt::route('import', 'tenant.knowledge.import')->name('import');
                    Route::get('import/template/{type}', KnowledgeImportTemplateController::class)
                        ->name('import.template');
                    Route::get('documents/{document}/download', KnowledgeDocumentDownloadController::class)
                        ->name('documents.download');
                });

                Route::prefix('ai')->name('ai.')->group(function () {
                    Volt::route('configuration', 'tenant.ai.configuration')->name('configuration');
                });

                Route::middleware('tenant.integrations')->prefix('integrations')->name('integrations.')->group(function () {
                    Volt::route('/', 'tenant.integrations.index')->name('index');
                    Volt::route('whatsapp', 'tenant.integrations.whatsapp')->name('whatsapp');
                    Volt::route('whatsapp/templates', 'tenant.integrations.whatsapp-templates')->name('whatsapp.templates');
                    Volt::route('whatsapp/events', 'tenant.integrations.whatsapp-events')->name('whatsapp.events');
                });

                Route::middleware('tenant.lead.manager')->group(function () {
                    Volt::route('leads', 'tenant.leads.index')->name('leads.index');
                    Volt::route('leads/create', 'tenant.leads.create')->name('leads.create');
                    Volt::route('leads/{lead}', 'tenant.leads.show')->name('leads.show');
                    Volt::route('counsellors', 'tenant.counsellors.index')->name('counsellors.index');
                    Volt::route('counsellors/create', 'tenant.counsellors.create')->name('counsellors.create');
                    Volt::route('conversations', 'tenant.conversations.index')->name('conversations.index');
                    Volt::route('conversations/{conversation}', 'tenant.conversations.show')->name('conversations.show');
                });
            });
        });

    Route::prefix('app/{tenant}/workspace')
        ->middleware(['tenant.resolve', 'counsellor.workspace', 'counsellor.subscription'])
        ->name('workspace.')
        ->group(function () {
            Volt::route('/', 'workspace.dashboard')->name('dashboard');
            Volt::route('leads', 'workspace.leads.index')->name('leads.index');
            Volt::route('leads/{lead}', 'workspace.leads.show')->name('leads.show');
            Volt::route('follow-ups', 'workspace.follow-ups.index')->name('follow-ups.index');
            Volt::route('conversations', 'workspace.conversations.index')->name('conversations.index');
            Volt::route('conversations/{conversation}', 'workspace.conversations.show')->name('conversations.show');
        });
});

require __DIR__.'/auth.php';
