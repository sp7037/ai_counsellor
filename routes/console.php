<?php

use App\Console\Commands\MaintainSubscriptionsCommand;
use App\Console\Commands\ReconcilePaymentOrdersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MaintainSubscriptionsCommand::class)->daily();
Schedule::command(ReconcilePaymentOrdersCommand::class)->hourly();
