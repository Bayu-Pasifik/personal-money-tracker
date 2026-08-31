<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SummaryController extends Controller
{
    /**
     * PRD.md FR-3.1 & FR-3.2: ringkasan bulanan + breakdown per kategori.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $transactions = $request->user()->transactions()
            ->with('category')
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $income = (int) $transactions->where('type', 'income')->sum('amount');
        $expense = (int) $transactions->where('type', 'expense')->sum('amount');

        $byCategory = $transactions
            ->groupBy('category_id')
            ->map(function ($group) {
                $category = $group->first()->category;

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'color_key' => $category->color_key,
                    'type' => $category->type,
                    'total' => (int) $group->sum('amount'),
                ];
            })
            ->values();

        return response()->json([
            'month' => $month->format('Y-m'),
            'total_income' => $income,
            'total_expense' => $expense,
            'balance' => $income - $expense,
            'by_category' => $byCategory,
        ]);
    }
}
