<?php

namespace App\Console\Commands;

use App\Services\Billing\PaymentOrderService;
use Illuminate\Console\Command;

class ReconcilePaymentOrdersCommand extends Command
{
    protected $signature = 'payments:reconcile {--batch=50 : Maximum orders to expire per run}';

    protected $description = 'Expire abandoned payment orders (reconciliation of stale checkouts)';

    public function handle(PaymentOrderService $orders): int
    {
        $count = $orders->expireStaleOrders((int) $this->option('batch'));
        $this->info("Expired {$count} abandoned payment order(s).");

        return self::SUCCESS;
    }
}
