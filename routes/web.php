<?php

use App\Http\Controllers\Tenant\KnowledgeDocumentDownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('app/select-tenant', 'tenant.select')->name('tenant.select');

    Route::prefix('platform')
        ->middleware('platform.admin')
        ->name('platform.')
        ->group(function () {
            Volt::route('/', 'platform.overview')->name('overview');
            Volt::route('tenants', 'platform.tenants.index')->name('tenants.index');
            Volt::route('tenants/create', 'platform.tenants.create')->name('tenants.create');
            Volt::route('tenants/{tenant}', 'platform.tenants.show')->name('tenants.show');
            Volt::route('ai-operations', 'platform.ai-operations.index')->name('ai-operations.index');
            Volt::route('usage', 'platform.usage.index')->name('usage.index');
            Volt::route('audit-logs', 'platform.audit-logs.index')->name('audit-logs.index');
            Volt::route('settings', 'platform.settings.index')->name('settings.index');
            Volt::route('failed-runs', 'platform.failed-runs.index')->name('failed-runs.index');
            Volt::route('system-health', 'platform.system-health.index')->name('system-health.index');
        });

    Route::prefix('app/{tenant}')
        ->middleware('tenant.resolve')
        ->name('tenant.')
        ->group(function () {
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
                Route::get('documents/{document}/download', KnowledgeDocumentDownloadController::class)
                    ->name('documents.download');
            });

            Route::prefix('ai')->name('ai.')->group(function () {
                Volt::route('configuration', 'tenant.ai.configuration')->name('configuration');
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

    Route::prefix('app/{tenant}/workspace')
        ->middleware(['tenant.resolve', 'counsellor.workspace'])
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
