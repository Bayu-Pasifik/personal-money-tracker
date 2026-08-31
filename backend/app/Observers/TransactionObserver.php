<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\BudgetWarningService;

class TransactionObserver
{
    public function __construct(private readonly BudgetWarningService $budgetWarning) {}

    public function created(Transaction $transaction): void
    {
        $this->budgetWarning->checkAndNotify($transaction);
    }
}
