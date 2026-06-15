<?php

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
            Volt::route('tenants', 'platform.tenants.index')->name('tenants.index');
            Volt::route('tenants/create', 'platform.tenants.create')->name('tenants.create');
            Volt::route('tenants/{tenant}', 'platform.tenants.show')->name('tenants.show');
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
        });
});

require __DIR__.'/auth.php';
